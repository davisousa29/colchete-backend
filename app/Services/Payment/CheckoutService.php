<?php

namespace App\Services\Payment;

use App\Exceptions\CheckoutException;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Document;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private PaymentGateway $gateway,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * Cria uma cobrança PIX para o plano escolhido e registra em `payments`.
     * Se já existir uma cobrança PIX pendente e não vencida do mesmo plano, reaproveita.
     * Não exige assinatura prévia: o registro de assinatura nasce na ativação.
     */
    public function createPixCharge(User $user, string $planSlug): Payment
    {
        $plan = Plan::active()->where('slug', $planSlug)->first();

        if (!$plan) {
            throw new \InvalidArgumentException("Plano inválido: {$planSlug}");
        }

        // Só reaproveita se a cobrança ainda bate com o plano atual:
        // se o preço ou os dias mudaram, gera uma nova com os valores de hoje
        $pending = Payment::where('user_id', $user->id)
            ->where('plan', $plan->slug)
            ->where('payment_method', 'pix')
            ->where('status', 'pending')
            ->where('value', $plan->price)
            ->where('days', $plan->days)
            ->whereDate('due_date', '>=', today())
            ->latest()
            ->first();

        if ($pending) {
            return $pending;
        }

        // O Asaas exige CPF para cobrar: avisa a pessoa antes de chamar a API
        if (!Document::isValidCpf($user->cpf)) {
            throw new CheckoutException('Para pagar, complete seu cadastro com um CPF válido.');
        }

        // Cria ou atualiza o cliente no Asaas: nome, e-mail, CPF e celular sempre em dia
        $customerId = $this->gateway->syncCustomer($user, $user->gateway_customer_id);

        if ($customerId !== $user->gateway_customer_id) {
            $user->gateway_customer_id = $customerId;
            $user->save();
        }

        $charge = $this->gateway->createPayment([
            'customer_id'        => $customerId,
            'value'              => (float) $plan->price,
            'due_date'           => now()->addDay()->toDateString(),
            'description'        => "Assinatura Colchete — plano {$plan->name}",
            'external_reference' => $user->id,
        ]);

        return Payment::create([
            'user_id'            => $user->id,
            'gateway'            => 'asaas',
            'gateway_payment_id' => $charge['gateway_payment_id'],
            'plan'               => $plan->slug,
            'days'               => $plan->days, // cópia: vale o que foi combinado na compra
            'payment_method'     => 'pix',
            'value'              => $charge['value'],
            'status'             => 'pending',
            'due_date'           => $charge['due_date'],
            'invoice_url'        => $charge['invoice_url'],
        ]);
    }

    /**
     * Processa a confirmação de pagamento vinda do webhook.
     * Idempotente: se o Asaas reenviar o mesmo evento, não ativa de novo.
     */
    public function confirmPayment(string $gatewayPaymentId, ?string $externalReference): string
    {
        return DB::transaction(function () use ($gatewayPaymentId, $externalReference) {
            $payment = Payment::where('gateway_payment_id', $gatewayPaymentId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return 'not_found';
            }

            // Conta excluída: o pagamento foi anonimizado e não há quem ativar
            if (!$payment->user_id) {
                return 'account_deleted';
            }

            if ($payment->status === 'received') {
                return 'already_processed';
            }

            if ($externalReference && $externalReference !== $payment->user_id) {
                return 'reference_mismatch';
            }

            $payment->update([
                'status'  => 'received',
                'paid_at' => now(),
            ]);

            // Cria a assinatura se a pessoa ainda não tinha uma
            $this->subscriptions->activate(
                $payment->user,
                $payment->plan,
                $payment->days,
                $payment->payment_method
            );

            return 'activated';
        });
    }
}
