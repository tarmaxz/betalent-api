<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private array $gatewayInstances = [];

    public function __construct()
    {
        $this->registerGateways();
    }

    private function registerGateways(): void
    {
        // Registrar gateways disponíveis
        $this->gatewayInstances = [
            'Gateway1' => \App\Services\Gateways\Gateway1::class,
            'Gateway2' => \App\Services\Gateways\Gateway2::class,
        ];
    }

    public function processTransaction(array $data): array
    {
        try {
            $client = $this->getOrCreateClient($data['client']);
            // valor total baseado nos produtos
            $products = $this->validateProducts($data['products']);
            $totalAmount = $this->calculateTotalAmount($products);
            // gateways ativos ordenados por prioridade
            $gateways = Gateway::active()->ordered()->get();
            if ($gateways->isEmpty()) {
                throw new \Exception('Não há gateways ativos disponíveis.');
            }
            $lastError = null;
            $transaction = null;
            // processa pagamento em cada gateway
            foreach ($gateways as $gateway) {
                try {
                    $gatewayInstance = $this->getGatewayInstance($gateway);
                    if (!$gatewayInstance) {
                        Log::warning("Gateway {$gateway->name} não implementado");
                        continue;
                    }
                    $paymentData = array_merge($data['payment'], [
                        'amount' => $totalAmount,
                        'description' => "Pagamento por " . count($products) . " produtos",
                        'client_email' => $client->email,
                    ]);
                    $result = $gatewayInstance->processPayment($paymentData);
                    // transação
                    $transaction = Transaction::create([
                        'client_id' => $client->id,
                        'gateway_id' => $gateway->id,
                        'external_id' => $result['external_id'],
                        'status' => $result['success'] ? 'approved' : 'failed',
                        'amount' => $totalAmount,
                        'card_last_numbers' => $this->getLastFourDigits($data['payment']['card_number']),
                        'gateway_response' => $result['data'],
                    ]);
                    // Adicionar produtos
                    $this->attachProductsToTransaction($transaction, $products);
                    if ($result['success']) {
                        return [
                            'success' => true,
                            'transaction_id' => $transaction->id,
                            'external_id' => $result['external_id'],
                            'gateway' => $gateway->name,
                            'amount' => $totalAmount,
                            'status' => 'approved',
                            'message' => 'Pagamento efetuado com sucesso',
                        ];
                    }
                    $lastError = $result['message'];
                    Log::warning("Gateway {$gateway->name} failed: {$lastError}");
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::error("Error processing payment with {$gateway->name}: {$lastError}");
                    continue;
                }
            }
            return [
                'success' => false,
                'message' => 'All gateways failed. Last error: ' . $lastError,
                'transaction_id' => $transaction?->id,
            ];
        } catch (\Exception $e) {
            Log::error('Payment Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getOrCreateClient(array $clientData): Client
    {
        return Client::firstOrCreate(
            ['email' => $clientData['email']],
            ['name' => $clientData['name']]
        );
    }

    private function validateProducts(array $productsData): array
    {
        $products = [];
        foreach ($productsData as $item) {
            $product = Product::active()->find($item['product_id']);
            if (!$product) {
                throw new \Exception("Produto {$item['product_id']} não foi encontrado ou está desativado");
            }
            $products[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
            ];
        }
        return $products;
    }

    private function calculateTotalAmount(array $products): float
    {
        $total = 0;
        foreach ($products as $item) {
            $total += $item['product']->amount * $item['quantity'];
        }
        return $total;
    }

    private function attachProductsToTransaction(
        Transaction $transaction, 
        array $products
    ): void
    {
        foreach ($products as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];
            $unitPrice = $product->amount;
            $subtotal = $unitPrice * $quantity;
            $transaction->products()->attach($product->id, [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }
    }

    private function getGatewayInstance(Gateway $gateway): ?PaymentGatewayInterface
    {
        $gatewayClass = $this->gatewayInstances[$gateway->name] ?? null;
        if (!$gatewayClass || !class_exists($gatewayClass)) {
            return null;
        }
        return new $gatewayClass($gateway->config ?? []);
    }

    private function getLastFourDigits(string $cardNumber): string
    {
        return substr(str_replace(' ', '', $cardNumber), -4);
    }

    public function refundTransaction(int $transactionId): array
    {
        try {
            $transaction = Transaction::with(['gateway'])->find($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transação não encontrada',
                ];
            }
            if ($transaction->status === 'cancelled') {
                return [
                    'success' => false,
                    'message' => 'Transação já cancelada',
                ];
            }
            if ($transaction->status !== 'approved') {
                return [
                    'success' => false,
                    'message' => 'Somente transações aprovadas podem ser reembolsadas.',
                ];
            }
            $gatewayInstance = $this->getGatewayInstance($transaction->gateway);
            if (!$gatewayInstance) {
                return [
                    'success' => false,
                    'message' => 'Gateway não disponível',
                ];
            }
            // Verificar se o gateway tem método de refund
            if (!method_exists($gatewayInstance, 'refund')) {
                return [
                    'success' => false,
                    'message' => 'A Gateway não oferece suporte a reembolsos.',
                ];
            }
            $result = $gatewayInstance->refund($transaction->external_id);
            if ($result['success']) {
                $transaction->update([
                    'status' => 'cancelled',
                ]);

                return [
                    'success' => true,
                    'message' => 'Reembolso processado com sucesso',
                    'transaction_id' => $transaction->id,
                ];
            }
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Falha no reembolso',
            ];
        } catch (\Exception $e) {
            Log::error('Refund Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
