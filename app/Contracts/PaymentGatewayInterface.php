<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Process payment through the gateway
     *
     * @param array $paymentData
     * @return array ['success' => bool, 'external_id' => string|null, 'message' => string, 'data' => array]
     */
    public function processPayment(array $paymentData): array;

    /**
     * Get gateway name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Validate payment data
     *
     * @param array $paymentData
     * @return bool
     */
    public function validatePaymentData(array $paymentData): bool;
}
