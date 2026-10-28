<?php

namespace App\Services;

use App\Exceptions\CadastroException;
use App\Mail\CodigoCadastroMail;
use App\Models\CadastroPendente;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CadastroService
{
    public const VALIDADE_MINUTOS           = 10;
    public const MAX_TENTATIVAS             = 5;
    public const INTERVALO_REENVIO_SEGUNDOS = 60;

    public function __construct(
        private TermosService $termos,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * Guarda o cadastro como pendente e envia o código.
     * Retorna quantos segundos faltam para poder pedir outro código.
     */
    public function iniciar(array $dados): int
    {
        if (!$this->termos->saoVigentes($dados['termos'])) {
            throw new CadastroException(
                'Os termos foram atualizados. Leia a versão mais recente.',
                409,
                ['code' => 'termos_desatualizados'],
            );
        }

        $pendente = CadastroPendente::firstOrNew(['email' => $dados['email']]);

        $pendente->dados = [
            'name'            => $dados['name'],
            'cpf'             => $dados['cpf'],
            'username'        => $dados['username'],
            'phone'           => $dados['phone'] ?? null,
            'whatsapp'        => $dados['whatsapp'] ?? null,
            'data_nascimento' => $dados['data_nascimento'],
            'password'        => Hash::make($dados['password']),
            'termos'          => array_values(array_unique($dados['termos'])),
        ];

        // Reenvio do formulário dentro do intervalo: atualiza os dados
        // e mantém válido o código que já foi enviado
        $restante = $this->segundosParaReenvio($pendente);

        if ($restante > 0) {
            $pendente->save();
            return $restante;
        }

        $this->enviarNovoCodigo($pendente);

        return self::INTERVALO_REENVIO_SEGUNDOS;
    }

    /**
     * Gera e envia um novo código para um cadastro pendente.
     */
    public function reenviar(string $email): int
    {
        $pendente = $this->buscarPendente($email);

        $restante = $this->segundosParaReenvio($pendente);

        if ($restante > 0) {
            throw new CadastroException(
                'Aguarde antes de solicitar um novo código.',
                429,
                ['segundos_restantes' => $restante],
            );
        }

        $this->enviarNovoCodigo($pendente);

        return self::INTERVALO_REENVIO_SEGUNDOS;
    }

    /**
     * Confere o código e cria a conta de verdade.
     */
    public function confirmar(string $email, string $codigo, ?string $ip, ?string $userAgent): User
    {
        $pendente = $this->buscarPendente($email);

        if ($pendente->expira_em->isPast()) {
            throw new CadastroException('Código expirado. Solicite um novo.');
        }

        if ($pendente->tentativas >= self::MAX_TENTATIVAS) {
            throw new CadastroException('Muitas tentativas. Solicite um novo código.', 429);
        }

        if (!Hash::check($codigo, $pendente->codigo)) {
            $pendente->increment('tentativas');
            throw new CadastroException('Código incorreto.');
        }

        $dados = $pendente->dados;

        // Alguém pode ter usado o e-mail, CPF ou username enquanto a pessoa abria o e-mail
        if ($conflito = $this->dadoIndisponivel($pendente->email, $dados)) {
            $pendente->delete();
            throw new CadastroException("{$conflito} Refaça o cadastro.");
        }

        try {
            return DB::transaction(function () use ($pendente, $dados, $ip, $userAgent) {
                $user = new User([
                    'name'            => $dados['name'],
                    'email'           => $pendente->email,
                    'cpf'             => $dados['cpf'],
                    'phone'           => $dados['phone'],
                    'username'        => $dados['username'],
                    'whatsapp'        => $dados['whatsapp'],
                    'role'            => 'consultor',
                    'password'        => $dados['password'], // já está em hash
                    'data_nascimento' => $dados['data_nascimento'],
                ]);
                $user->email_verified_at = now();
                $user->save();

                $this->subscriptions->createTrial($user);
                $this->termos->registrarAceite($user, $dados['termos'], $ip, $userAgent);

                $pendente->delete();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Corrida rara: outra conta ocupou o dado entre a conferência e o INSERT
            throw new CadastroException('Alguns dados já estão em uso. Refaça o cadastro.');
        }
    }

    // ── Auxiliares ────────────────────────────────────────────────────────────

    private function buscarPendente(string $email): CadastroPendente
    {
        $pendente = CadastroPendente::where('email', $email)->first();

        if (!$pendente) {
            throw new CadastroException(
                'Cadastro não encontrado ou expirado. Preencha o formulário novamente.',
                404,
            );
        }

        return $pendente;
    }

    private function enviarNovoCodigo(CadastroPendente $pendente): void
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Envia antes de gravar: se o e-mail falhar, nada muda no banco
        try {
            Mail::to($pendente->email)->send(new CodigoCadastroMail($codigo, self::VALIDADE_MINUTOS));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar código de cadastro', ['erro' => $e->getMessage()]);
            throw new CadastroException(
                'Não foi possível enviar o e-mail agora. Tente novamente em instantes.',
                502,
            );
        }

        $pendente->fill([
            'codigo'          => Hash::make($codigo),
            'expira_em'       => now()->addMinutes(self::VALIDADE_MINUTOS),
            'tentativas'      => 0,
            'ultimo_envio_em' => now(),
        ])->save();
    }

    private function segundosParaReenvio(CadastroPendente $pendente): int
    {
        if (!$pendente->exists || !$pendente->ultimo_envio_em) {
            return 0;
        }

        $passados = (int) $pendente->ultimo_envio_em->diffInSeconds(now());

        return max(0, self::INTERVALO_REENVIO_SEGUNDOS - $passados);
    }

    private function dadoIndisponivel(string $email, array $dados): ?string
    {
        return match (true) {
            User::where('email', $email)->exists()                 => 'Não é possível utilizar este email.',
            User::where('cpf', $dados['cpf'])->exists()           => 'Não é possível utilizar este CPF.',
            User::where('username', $dados['username'])->exists() => 'Este nome de usuário já foi escolhido.',
            default                                                => null,
        };
    }
}
