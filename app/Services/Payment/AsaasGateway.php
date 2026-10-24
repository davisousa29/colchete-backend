<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Support\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class AsaasGateway implements PaymentGateway
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.asaas.base_url');
        $this->apiKey  = config('services.asaas.api_key');
    }

    /**
     * Cliente HTTP pré-configurado com o header de autenticação do Asaas.
     */
    private function client()
    {
        return Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    public function syncCustomer(User $user, ?string $customerId): string
    {
        if ($customerId) {
            $response = $this->sendCustomer('put', "/customers/{$customerId}", $user);

            if ($response->successful()) {
                return $customerId;
            }

            // 404 = o cliente foi removido no painel do Asaas: cria um novo abaixo
            if ($response->status() !== 404) {
                throw new \Exception('Erro ao atualizar cliente no Asaas: ' . $response->body());
            }
        }

        $response = $this->sendCustomer('post', '/customers', $user);

        if (!$response->successful()) {
            throw new \Exception('Erro ao criar cliente no Asaas: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Envia os dados do cliente (criação ou atualização). Se o Asaas recusar o celular,
     * tenta de novo sem ele: o celular é opcional para cobrar.
     */
    private function sendCustomer(string $method, string $url, User $user): Response
    {
        $payload = [
            'name'    => $user->name,
            'email'   => $user->email,
            'cpfCnpj' => Document::onlyDigits($user->cpf),
        ];

        $phone = Document::onlyDigits($user->whatsapp ?? $user->phone ?? '');
        if (strlen($phone) === 11) {
            $payload['mobilePhone'] = $phone;
        }

        $response = $this->client()->{$method}($url, $payload);

        if (
            !$response->successful()
            && isset($payload['mobilePhone'])
            && collect($response->json('errors', []))->contains('code', 'invalid_mobilePhone')
        ) {
            unset($payload['mobilePhone']);
            $response = $this->client()->{$method}($url, $payload);
        }

        return $response;
    }

    public function createPayment(array $data): array
    {
        $response = $this->client()->post('/payments', [
            'customer'          => $data['customer_id'],
            'billingType'       => 'PIX',
            'value'             => $data['value'],
            'dueDate'           => $data['due_date'],
            'description'       => $data['description'] ?? 'Assinatura Colchete',
            'externalReference' => $data['external_reference'] ?? null,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Erro ao criar cobrança PIX no Asaas: ' . $response->body());
        }

        $payment = $response->json();

        return [
            'gateway_payment_id' => $payment['id'],
            'status'             => $payment['status'],
            'value'              => $payment['value'],
            'due_date'           => $payment['dueDate'],
            'invoice_url'        => $payment['invoiceUrl'] ?? null,
        ];
    }

    /**
     * Busca o QR Code PIX de uma cobrança já criada.
     * (O Asaas leva alguns segundos para gerar o QR após criar a cobrança,
     *  por isso é uma chamada separada.)
     */
    public function getPixQrCode(string $gatewayPaymentId): array
    {
        $response = $this->client()->get("/payments/{$gatewayPaymentId}/pixQrCode");

        if (!$response->successful()) {
            throw new \Exception('Erro ao buscar QR Code PIX no Asaas: ' . $response->body());
        }

        $qr = $response->json();

        return [
            'pix_qr_code'    => $qr['encodedImage'] ?? null,  // imagem base64
            'pix_copy_paste' => $qr['payload'] ?? null,        // código copia-e-cola
            'pix_expiration' => $qr['expirationDate'] ?? null,
        ];
    }

    public function createSubscription(array $data): array
    {
        // TODO: implementar assinatura recorrente (cartão)
        throw new \Exception('createSubscription ainda não implementado.');
    }

    public function cancelSubscription(string $gatewaySubscriptionId): bool
    {
        // TODO: implementar cancelamento
        throw new \Exception('cancelSubscription ainda não implementado.');
    }

    public function parseWebhook(array $payload): array
    {
        $event   = $payload['event'] ?? null;
        $payment = $payload['payment'] ?? [];

        return [
            'event'              => $event,
            'gateway_payment_id' => $payment['id'] ?? null,
            'status'             => $payment['status'] ?? null,
            'external_reference' => $payment['externalReference'] ?? null,
            'value'              => $payment['value'] ?? null,
        ];
    }
}
