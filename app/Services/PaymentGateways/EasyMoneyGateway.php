<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentErrorService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EasyMoneyGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'http://localhost:3000';
    private const GATEWAY_NAME = 'easy_money';

    // Error Messages
    private const ERROR_MESSAGES = [
        'decimal_amount_not_supported' => 'EasyMoney cannot process decimal amounts. The amount must be an integer.',
        'invalid_currency' => 'Currency must have exactly 3 characters (e.g., USD, EUR).',
        'invalid_amount' => 'Amount must be greater than zero.',
        'decimal_or_data_error' => 'EasyMoney cannot process decimal amounts or there is an error in the sent data.',
        'processing_error' => 'Error in payment processing.',
        'unexpected_response' => 'Unexpected response from EasyMoney server.',
        'connection_error' => 'Connection error with EasyMoney server.',
    ];

    public function processPayment(float $amount, string $currency, array $additionalData = []): array
    {
        $validationError = $this->validatePaymentData($amount, $currency);
        if ($validationError) {
            return $validationError;
        }

        $requestData = [
            'amount' => $amount,
            'currency' => $currency,
        ];

        try {
            $response = Http::post(self::BASE_URL . '/process', $requestData);
            
            $responseBody = $response->body();
            $statusCode = $response->status();
            
            $success = $response->successful() && $responseBody === 'ok';
            
            if ($success) {
                return PaymentErrorService::createSuccessResponse(
                    $statusCode,
                    $responseBody
                );
            }

            if ($responseBody === 'error') {
                $errorType = $statusCode === 400 ? 'decimal_or_data_error' : 'processing_error';
            } else {
                $errorType = 'unexpected_response';
            }

            return $this->createErrorResponse($errorType, $statusCode, $responseBody);
        } catch (Exception $e) {
            Log::error('EasyMoney payment processing error', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $this->createErrorResponse('connection_error');
        }
    }

    private function isIntegerAmount(float $amount): bool
    {
        return floor($amount) === $amount;
    }

    private function validatePaymentData(float $amount, string $currency): ?array
    {
        $validations = [
            fn() => !$this->isIntegerAmount($amount) ? 'decimal_amount_not_supported' : null,
            fn() => empty($currency) || strlen($currency) !== 3 ? 'invalid_currency' : null,
            fn() => $amount <= 0 ? 'invalid_amount' : null,
        ];

        foreach ($validations as $validation) {
            $errorType = $validation();
            if ($errorType) {
                return $this->createErrorResponse($errorType);
            }
        }

        return null;
    }

    private function createErrorResponse(string $errorType, ?int $statusCode = null, ?string $responseBody = null): array
    {
        $statusCode = $statusCode ?? match ($errorType) {
            'decimal_amount_not_supported' => 400,
            'invalid_currency' => 400,
            'invalid_amount' => 400,
            'connection_error' => 500,
            default => 500,
        };

        $responseBody = $responseBody ?? match ($errorType) {
            'decimal_amount_not_supported' => 'error',
            'invalid_currency' => 'error',
            'invalid_amount' => 'error',
            'connection_error' => 'Internal server error',
            default => 'error',
        };

        return PaymentErrorService::createErrorResponse(
            $statusCode,
            $responseBody,
            $errorType,
            self::ERROR_MESSAGES[$errorType] ?? 'Unknown error occurred with EasyMoney.'
        );
    }

    public function getGatewayName(): string
    {
        return self::GATEWAY_NAME;
    }

    public function handleWebhook(array $webhookData): array
    {
        return [
            'success' => false,
            'message' => 'EasyMoney does not support webhooks',
        ];
    }
}
