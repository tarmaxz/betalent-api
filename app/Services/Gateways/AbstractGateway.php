<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;

abstract class AbstractGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function validatePaymentData(array $paymentData): bool
    {
        $requiredFields = [
            'amount', 
            'card_number',
            'card_holder',
            'card_expiry','card_cvv'
        ];
        foreach ($requiredFields as $field) {
            if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                return false;
            }
        }
        return true;
    }

    protected function getLastFourDigits(string $cardNumber): string
    {
        return substr(str_replace(' ', '', $cardNumber), -4);
    }
}
