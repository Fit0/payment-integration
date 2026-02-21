<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Process a payment throught the gateway
     *
     * @param float $amount
     * @param string $currency
     * @param array $additionalData
     * @return array
     */
    public function processPayment(float $amount, string $currency, array $additionalData): array;

    /**
     * Get the gateway name
     *
     * @return string
     */
    public function getGatewayName(): string;

    /**
     * Handle webhook response from the gateway
     *
     * @param array $webhookData
     * @return array
     */
    public function handleWebhook(array $webhookData): array;
}
