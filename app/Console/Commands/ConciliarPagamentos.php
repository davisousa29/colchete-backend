<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payment\CheckoutService;
use Illuminate\Console\Command;

class ConciliarPagamentos extends Command
{
    protected $signature = 'payments:conciliar';

    protected $description = 'Confere no gateway as cobranças PIX pendentes e confirma as que já foram pagas (rede de segurança do webhook)';

    public function handle(CheckoutService $checkout): int
    {
        // Só as recentes: cobrança vencida há mais de 3 dias não será mais paga
        $pendentes = Payment::where('status', 'pending')
            ->whereNotNull('gateway_payment_id')
            ->whereNotNull('user_id')
            ->whereDate('due_date', '>=', today()->subDays(3))
            ->get();

        $confirmados = 0;

        foreach ($pendentes as $payment) {
            if ($checkout->syncPending($payment, forcar: true)->status === 'received') {
                $confirmados++;
            }
        }

        $this->info("{$pendentes->count()} pendente(s) conferida(s), {$confirmados} confirmada(s).");

        return self::SUCCESS;
    }
}
