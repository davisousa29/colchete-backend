<?php

namespace App\Console\Commands;

use App\Exceptions\ExclusaoContaException;
use App\Models\User;
use App\Services\ExclusaoContaService;
use Illuminate\Console\Command;

/**
 * Exclusão administrativa de contas, pelo mesmo caminho da tela "Excluir conta":
 * cancela cobranças em aberto no gateway, anonimiza pagamentos e apaga o resto
 * pela cascata. Para pedidos ao suporte (LGPD), contas criadas com o Google
 * (sem senha) e limpeza de contas de teste.
 */
class ExcluirConta extends Command
{
    protected $signature = 'contas:excluir
        {email : E-mail da conta a excluir}
        {--sem-email : Não envia o e-mail de confirmação da exclusão}';

    protected $description = 'Exclui definitivamente uma conta e seus dados (uso administrativo)';

    public function handle(ExclusaoContaService $exclusao): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $user  = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Nenhuma conta com o e-mail {$email}.");
            return self::FAILURE;
        }

        $this->table(['Nome', 'E-mail', 'Criada em'], [[
            $user->name,
            $user->email,
            $user->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
        ]]);

        if (!$this->confirm('Excluir esta conta e TODOS os dados dela, definitivamente?')) {
            $this->info('Nada foi feito.');
            return self::SUCCESS;
        }

        try {
            $exclusao->apagar($user, 'admin', !$this->option('sem-email'));
        } catch (ExclusaoContaException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Conta excluída.' . ($this->option('sem-email') ? '' : ' Confirmação enviada por e-mail.'));

        return self::SUCCESS;
    }
}
