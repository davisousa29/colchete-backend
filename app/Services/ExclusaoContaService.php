<?php

namespace App\Services;

use App\Exceptions\ExclusaoContaException;
use App\Mail\SubscriptionNoticeMail;
use App\Models\CadastroPendente;
use App\Models\CodigoRecuperacao;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Exclusão da conta a pedido do próprio usuário (exigência da Apple, do Google e da LGPD).
 *
 * - Pagamentos: anonimizados (continuam registrados, sem ligação com a pessoa).
 * - Todo o resto que pertence ao usuário sai pelo ON DELETE CASCADE do banco:
 *   perfil, 2FA, assinatura, contratos, fazendas, formulações, projeções,
 *   notificações e aceites dos termos.
 */
class ExclusaoContaService
{
    // Cobranças que ainda podem ser pagas e precisam ser canceladas no gateway
    private const STATUS_EM_ABERTO = ['pending', 'overdue'];

    public function __construct(private PaymentGateway $gateway) {}

    public function excluir(User $user, string $senha): void
    {
        if (!$user->password) {
            throw new ExclusaoContaException(
                'Esta conta foi criada com o Google e não tem senha. Para excluí-la, escreva para colchete.gestaodefazendas@gmail.com.'
            );
        }

        if (!Hash::check($senha, $user->password)) {
            throw new ExclusaoContaException('Senha incorreta.');
        }

        // Antes de apagar: nenhuma cobrança pode continuar pagável
        $this->cancelarCobrancasEmAberto($user);

        $userId = $user->id;
        $email  = $user->email;
        $nome   = $user->name;

        DB::transaction(function () use ($user) {
            // Anonimiza: valor, data e id da cobrança ficam; a ligação com a pessoa sai.
            // A fatura do Asaas mostra o nome do cliente, então o link também sai.
            Payment::where('user_id', $user->id)->update([
                'user_id'     => null,
                'invoice_url' => null,
            ]);

            // Dados pessoais guardados pelo e-mail, fora da cascata
            CodigoRecuperacao::where('email', $user->email)->delete();
            CadastroPendente::where('email', $user->email)->delete();

            // O restante sai pelo ON DELETE CASCADE
            $user->delete();
        });

        Log::info('Conta excluída pelo próprio usuário', ['user_id' => $userId]);

        $this->confirmarPorEmail($email, $nome);
    }

    private function cancelarCobrancasEmAberto(User $user): void
    {
        $emAberto = Payment::where('user_id', $user->id)
            ->whereIn('status', self::STATUS_EM_ABERTO)
            ->get();

        foreach ($emAberto as $payment) {
            try {
                $this->gateway->cancelPayment($payment->gateway_payment_id);
            } catch (\Throwable $e) {
                Log::error('Falha ao cancelar cobrança na exclusão de conta', [
                    'payment_id' => $payment->id,
                    'erro'       => $e->getMessage(),
                ]);

                // Não apaga a conta com uma cobrança ainda pagável no gateway
                throw new ExclusaoContaException(
                    'Não foi possível concluir a exclusão agora. Tente novamente em instantes.',
                    502,
                );
            }

            $payment->update(['status' => 'canceled']);
        }
    }

    private function confirmarPorEmail(string $email, string $nome): void
    {
        try {
            Mail::to($email)->send(new SubscriptionNoticeMail(
                $nome,
                'Sua conta no Colchete foi excluída',
                'Confirmamos a exclusão da sua conta e de todos os seus dados de trabalho. '
                . 'Se você não pediu esta exclusão, responda este e-mail.',
            ));
        } catch (\Throwable $e) {
            // A conta já foi excluída: a falha do e-mail não desfaz nada
            Log::warning('Falha ao enviar e-mail de confirmação de exclusão', ['erro' => $e->getMessage()]);
        }
    }
}
