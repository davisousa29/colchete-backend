<?php

namespace App\Services\Payment;

use App\Models\User;

interface PaymentGateway
{
    /**
     * Garante o cliente no gateway com os dados atuais do usuário:
     * cria se $customerId for null (ou se ele não existir mais lá) e atualiza se existir.
     * Retorna o ID do cliente no gateway.
     */
    public function syncCustomer(User $user, ?string $customerId): string;

    /**
     * Busca o QR Code PIX de uma cobrança já criada.
     */
    public function getPixQrCode(string $gatewayPaymentId): array;

    /**
     * Cria uma assinatura recorrente (ex: mensal no cartão).
     * Retorna os dados da assinatura no gateway.
     */
    public function createSubscription(array $data): array;

    /**
     * Cancela uma assinatura recorrente no gateway.
     */
    public function cancelSubscription(string $gatewaySubscriptionId): bool;

    /**
     * Valida e interpreta um webhook recebido do gateway.
     * Retorna um formato padronizado (evento + dados), independente do gateway.
     */
    public function parseWebhook(array $payload): array;
}
