<?php

namespace App\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Gateway2 extends AbstractGateway
{
    public function getName(): string
    {
        return 'Gateway2';
    }

    public function processPayment(array $paymentData): array
    {
        try {
            if (!$this->validatePaymentData($paymentData)) {
                return [
                    'success' => false,
                    'external_id' => null,
                    'message' => 'Dados de pagamento inválidos',
                    'data' => [],
                ];
            }
            $baseUrl = $this->config['base_url'] ?? env('GATEWAY2_URL', 'http://gateway-mock:3002');
            $authToken = $this->config['auth_token'] ?? env('GATEWAY2_AUTH_TOKEN', 'tk_f2198cc671b5289fa856');
            $authSecret = $this->config['auth_secret'] ?? env('GATEWAY2_AUTH_SECRET', '3d15e8ed6131446ea7e3456728b1211f');
            $response = Http::withHeaders([
                'Gateway-Auth-Token' => $authToken,
                'Gateway-Auth-Secret' => $authSecret,
            ])->post("{$baseUrl}/transacoes", [
                'valor' => (int)($paymentData['amount'] * 100),
                'nome' => $paymentData['card_holder'],
                'email' => $paymentData['client_email'] ?? 'customer@example.com',
                'numeroCartao' => str_replace(' ', '', $paymentData['card_number']),
                'cvv' => $paymentData['card_cvv'],
            ]);
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'external_id' => (string)($data['id'] ?? $data['transacaoId'] ?? uniqid('gw2_')),
                    'message' => 'Pagamento processado com sucesso',
                    'data' => $data,
                ];
            }
            $errorData = $response->json() ?? [];
            return [
                'success' => false,
                'external_id' => null,
                'message' => $errorData['message'] ?? $errorData['mensagem'] ?? 'Gateway2 payment failed (HTTP ' . $response->status() . ')',
                'data' => $errorData,
            ];

        } catch (\Exception $e) {
            Log::error('Gateway2 Error: ' . $e->getMessage());
            return [
                'success' => false,
                'external_id' => null,
                'message' => 'Gateway2 error: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function refund(string $externalId): array
    {
        try {
            $baseUrl = $this->config['base_url'] ?? env('GATEWAY2_URL', 'http://gateway-mock:3002');
            $authToken = $this->config['auth_token'] ?? env('GATEWAY2_AUTH_TOKEN', 'tk_f2198cc671b5289fa856');
            $authSecret = $this->config['auth_secret'] ?? env('GATEWAY2_AUTH_SECRET', '3d15e8ed6131446ea7e3456728b1211f');
            $response = Http::withHeaders([
                'Gateway-Auth-Token' => $authToken,
                'Gateway-Auth-Secret' => $authSecret,
            ])->post("{$baseUrl}/transacoes/reembolso", [
                'id' => $externalId,
            ]);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Reembolso processado com sucesso',
                    'data' => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json()['message'] ?? $response->json()['mensagem'] ?? 'Gateway2 refund failed',
                'data' => $response->json() ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Gateway2 Refund Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gateway2 refund error: ' . $e->getMessage(),
            ];
        }
    }
}
