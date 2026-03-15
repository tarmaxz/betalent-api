<?php

namespace App\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Gateway1 extends AbstractGateway
{
    private ?string $bearerToken = null;

    public function getName(): string
    {
        return 'Gateway1';
    }

    private function authenticate(): bool
    {
        try {
            $baseUrl = $this->config['base_url'] ?? env('GATEWAY1_URL', 'http://gateway-mock:3001');
            $response = Http::post("{$baseUrl}/login", [
                'email' => $this->config['email'] ?? env('GATEWAY1_EMAIL', 'dev@betalent.tech'),
                'token' => $this->config['token'] ?? env('GATEWAY1_TOKEN', 'FEC9BB078BF338F464F96B48089EB498'),
            ]);
            if ($response->successful()) {
                $data = $response->json();
                $this->bearerToken = $data['token'] ?? null;
                return $this->bearerToken !== null;
            }
            Log::warning('Gateway1 Auth failed: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Gateway1 Authentication Error: ' . $e->getMessage());
            return false;
        }
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
            if (!$this->authenticate()) {
                return [
                    'success' => false,
                    'external_id' => null,
                    'message' => 'Gateway1 authentication failed',
                    'data' => [],
                ];
            }
            $baseUrl = $this->config['base_url'] ?? env('GATEWAY1_URL', 'http://gateway-mock:3001');
            $response = Http::withToken($this->bearerToken)
                ->post("{$baseUrl}/transactions", [
                    'amount' => (int)($paymentData['amount'] * 100),
                    'name' => $paymentData['card_holder'],
                    'email' => $paymentData['client_email'] ?? 'customer@example.com',
                    'cardNumber' => str_replace(' ', '', $paymentData['card_number']),
                    'cvv' => $paymentData['card_cvv'],
                ]);
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'external_id' => (string)($data['id'] ?? $data['transactionId'] ?? uniqid('gw1_')),
                    'message' => 'Pagamento processado com sucesso',
                    'data' => $data,
                ];
            }
            $errorData = $response->json() ?? [];
            return [
                'success' => false,
                'external_id' => null,
                'message' => $errorData['message'] ?? 'Gateway1 payment failed (HTTP ' . $response->status() . ')',
                'data' => $errorData,
            ];

        } catch (\Exception $e) {
            Log::error('Gateway1 Error: ' . $e->getMessage());
            return [
                'success' => false,
                'external_id' => null,
                'message' => 'Gateway1 error: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function refund(string $externalId): array
    {
        try {
            if (!$this->authenticate()) {
                return [
                    'success' => false,
                    'message' => 'Gateway1 authentication failed',
                ];
            }
            $baseUrl = $this->config['base_url'] ?? env('GATEWAY1_URL', 'http://gateway-mock:3001');
            $response = Http::withToken($this->bearerToken)
                ->post("{$baseUrl}/transactions/{$externalId}/charge_back");
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Reembolso processado com sucesso',
                    'data' => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Gateway1 refund failed',
                'data' => $response->json() ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Gateway1 Refund Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gateway1 refund error: ' . $e->getMessage(),
            ];
        }
    }
}
