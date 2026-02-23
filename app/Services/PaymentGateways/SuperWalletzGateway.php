<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentErrorService;
use App\Models\WebhookLog;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction;


class SuperWalletzGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'http://localhost:3003';
    private const GATEWAY_NAME = 'super_walletz';
    private const WEBHOOK_TIMEOUT_SECONDS = 30;

    // Error Messages
    private const ERROR_MESSAGES = [
        'missing_callback_url' => 'SuperWalletz requires a callback URL to process the payment.',
        'invalid_callback_url' => 'The provided callback URL is not valid.',
        'invalid_currency' => 'Currency must have exactly 3 characters (e.g., USD, EUR).',
        'invalid_amount' => 'Amount must be greater than zero.',
        'amount_exceeds_limit' => 'Amount exceeds the maximum allowed limit of 1,000,000.',
        'invalid_data' => 'Invalid data sent to SuperWalletz. Verify amount, currency and callback_url.',
        'authentication_error' => 'Authentication error with SuperWalletz.',
        'rate_limit_exceeded' => 'Rate limit exceeded. Please try again later.',
        'server_error' => 'Internal SuperWalletz server error. Please try again later.',
        'payment_initiation_error' => 'Error initiating payment with SuperWalletz.',
        'connection_error' => 'Could not connect to SuperWalletz server. Check your internet connection.',
        'request_error' => 'Error in request to SuperWalletz.',
        'unexpected_error' => 'Unexpected error processing payment with SuperWalletz.',
        'invalid_response_format' => 'SuperWalletz response does not contain a valid transaction_id.',
    ];

    private const WEBHOOK_ERROR_MESSAGES = [
        'missing_transaction_id' => 'Webhook must contain a transaction_id.',
        'missing_status' => 'Webhook must contain a status.',
        'invalid_status' => 'Status is not valid. Allowed statuses: success, failed, pending, cancelled.',
        'invalid_transaction_id_format' => 'Transaction_id must be a non-empty string.',
        'webhook_processing_error' => 'Error processing SuperWalletz webhook.',
    ];

    public function processPayment(float $amount, string $currency, array $additionalData = []): array
    {
        $validationError = $this->validatePaymentData($amount, $currency, $additionalData);
        if ($validationError) {
            return $validationError;
        }

        $callbackUrl = $additionalData['callback_url'] ?? $this->generateCallbackUrl();
        
        $requestData = [
            'amount' => $amount,
            'currency' => $currency,
            'callback_url' => $callbackUrl,
        ];

        try {
            $response = Http::timeout(30)->post(self::BASE_URL . '/pay', $requestData);
            
            $responseBody = $response->body();
            $statusCode = $response->status();
            
            if ($response->successful()) {
                $decodedResponse = $response->json();
                
                if (!isset($decodedResponse['transaction_id'])) {
                    return $this->createErrorResponse('invalid_response_format');
                }

                $transactionId = $decodedResponse['transaction_id'];
                $this->waitForWebhookConfirmation($transactionId);
                $finalStatus = $this->getFinalTransactionStatus($transactionId);
                
                return PaymentErrorService::createSuccessResponse(
                    $statusCode,
                    $responseBody,
                    [
                        'transaction_id' => $decodedResponse['transaction_id'],
                        'status' => $finalStatus['status'],
                        'webhook_received' => $finalStatus['webhook_received'],
                        'processing_time' => $finalStatus['processing_time'],
                    ]
                );
            }

            $errorType = match ($statusCode) {
                400 => 'invalid_data',
                401 => 'authentication_error',
                429 => 'rate_limit_exceeded',
                default => ($statusCode >= 500 ? 'server_error' : 'payment_initiation_error'),
            };

            return $this->createErrorResponse($errorType, $statusCode, $responseBody);
        } catch (ConnectionException $e) {
            Log::error('SuperWalletz connection error', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency,
                'callback_url' => $callbackUrl,
            ]);

            return $this->createErrorResponse('connection_error');
        } catch (RequestException $e) {
            Log::error('SuperWalletz request error', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency,
                'callback_url' => $callbackUrl,
            ]);

            return $this->createErrorResponse('request_error');
        } catch (Exception $e) {
            Log::error('SuperWalletz payment processing error', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency,
                'callback_url' => $callbackUrl,
            ]);

            return $this->createErrorResponse('unexpected_error');
        }
    }

    private function validatePaymentData(float $amount, string $currency, array $additionalData): ?array
    {
        $callbackUrl = $additionalData['callback_url'] ?? null;
        
        $validations = [
            fn() => !$callbackUrl ? 'missing_callback_url' : null,
            fn() => $callbackUrl && !filter_var($callbackUrl, FILTER_VALIDATE_URL) ? 'invalid_callback_url' : null,
            fn() => empty($currency) || strlen($currency) !== 3 ? 'invalid_currency' : null,
            fn() => $amount <= 0 ? 'invalid_amount' : null,
            fn() => $amount > 1000000 ? 'amount_exceeds_limit' : null,
        ];

        foreach ($validations as $validation) {
            $errorType = $validation();
            if ($errorType) {
                return $this->createErrorResponse($errorType);
            }
        }

        return null;
    }

    public function getGatewayName(): string
    {
        return self::GATEWAY_NAME;
    }

    public function handleWebhook(array $webhookData): array
    {
        try {
            $webhookId = request()->get('id');
            if ($webhookId && !Cache::has("webhook_{$webhookId}")) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired webhook',
                    'error_type' => 'invalid_webhook_id',
                ];
            }

            if ($webhookId) {
                Cache::forget("webhook_{$webhookId}");
            }

            $validationError = $this->validateWebhookData($webhookData);

            if ($validationError) {
                $this->logWebhook($webhookData, $validationError, false);
                return $validationError;
            }

            $transactionId = $webhookData['transaction_id'];
            $status = $webhookData['status'];

            $this->updateTransactionFromWebhook($transactionId, $status);
            $this->logWebhook($webhookData, null, true);

            return [
                'success' => true,
                'message' => 'Webhook processed successfully',
                'transaction_id' => $transactionId,
                'status' => $status,
            ];
        } catch (Exception $e) {
            Log::error('SuperWalletz webhook processing error', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);

            $this->logWebhook($webhookData, $e->getMessage(), false);

            return [
                'success' => false,
                'message' => self::WEBHOOK_ERROR_MESSAGES['webhook_processing_error'],
                'error' => $e->getMessage(),
                'error_type' => 'webhook_processing_error',
            ];
        }
    }

    private function generateCallbackUrl(): string
    {
        $baseUrl = config('app.url') . '/api/webhooks/super-walletz';

        $webhookId = uniqid('wh_', true);
        Cache::put("webhook_{$webhookId}", true, self::WEBHOOK_TIMEOUT_SECONDS);
        
        return $baseUrl . "?id=" . $webhookId;
    }

    private function waitForWebhookConfirmation(string $transactionId): void
    {
        $startTime = time();
        $timeout = self::WEBHOOK_TIMEOUT_SECONDS;
        
        Log::info('Starting webhook confirmation wait', [
            'transaction_id' => $transactionId,
            'timeout' => $timeout,
        ]);
        
        while ((time() - $startTime) < $timeout) {
            $recentWebhook = WebhookLog::where('payment_gateway', self::GATEWAY_NAME)
                ->where('success', true)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($recentWebhook) {
                $webhookData = json_decode($recentWebhook->webhook_data, true);
                $webhookTransactionId = $webhookData['transaction_id'] ?? null;
                $webhookStatus = $webhookData['status'] ?? null;
                
                Log::info('Found recent webhook', [
                    'original_transaction_id' => $transactionId,
                    'webhook_transaction_id' => $webhookTransactionId,
                    'webhook_status' => $webhookStatus,
                    'webhook_created_at' => $recentWebhook->created_at,
                ]);
                
                // Si encontramos un webhook exitoso, consideramos que el pago fue procesado
                if ($webhookStatus === 'success') {
                    Log::info('Webhook confirmation received via webhook lookup', [
                        'transaction_id' => $transactionId,
                        'webhook_transaction_id' => $webhookTransactionId,
                        'total_wait_time' => time() - $startTime,
                    ]);
                    return;
                }
            }
            
            Log::info('Waiting for webhook', [
                'transaction_id' => $transactionId,
                'elapsed_time' => time() - $startTime,
            ]);
            
            sleep(1);
        }
        
        Log::warning('Webhook confirmation timeout', [
            'transaction_id' => $transactionId,
            'timeout' => $timeout,
        ]);
    }

    private function getFinalTransactionStatus(string $transactionId): array
    {
        // Primero buscar transacción por el transaction_id original
        $transaction = Transaction::where('gateway_transaction_id', $transactionId)->first();
        
        if (!$transaction) {
            // Si no encuentra, buscar webhooks recientes para determinar el estado
            $recentWebhook = WebhookLog::where('payment_gateway', self::GATEWAY_NAME)
                ->where('success', true)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($recentWebhook) {
                $webhookData = json_decode($recentWebhook->webhook_data, true);
                $webhookStatus = $webhookData['status'] ?? null;
                
                Log::info('Using webhook status for final status', [
                    'transaction_id' => $transactionId,
                    'webhook_status' => $webhookStatus,
                    'webhook_created_at' => $recentWebhook->created_at,
                ]);
                
                return [
                    'status' => $webhookStatus === 'success' ? 'completed' : 'failed',
                    'webhook_received' => true,
                    'processing_time' => $recentWebhook->created_at->diffInSeconds(now()->subSeconds(30)), // Aproximado
                    'gateway_transaction_id' => $webhookData['transaction_id'] ?? $transactionId,
                ];
            }
            
            return [
                'status' => 'failed',
                'webhook_received' => false,
                'processing_time' => 0,
                'gateway_transaction_id' => $transactionId,
            ];
        }

        return [
            'status' => $transaction->status,
            'webhook_received' => in_array($transaction->status, ['completed', 'failed']),
            'processing_time' => $transaction->updated_at->diffInSeconds($transaction->created_at),
            'gateway_transaction_id' => $transaction->gateway_transaction_id,
        ];
    }

    private function logWebhook(array $webhookData, ?string $errorMessage, bool $success): void
    {
        $existingWebhook = WebhookLog::where('payment_gateway', self::GATEWAY_NAME)
            ->where('webhook_data', json_encode($webhookData))
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($existingWebhook) {
            Log::info('Duplicate webhook ignored', [
                'gateway' => self::GATEWAY_NAME,
                'webhook_data' => $webhookData,
                'existing_webhook_id' => $existingWebhook->id,
            ]);
            return;
        }

        WebhookLog::create([
            'payment_gateway' => self::GATEWAY_NAME,
            'webhook_data' => json_encode($webhookData),
            'status_code' => $success ? 200 : 400,
            'response_message' => $errorMessage ?? 'Webhook processed successfully',
            'success' => $success,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Webhook logged', [
            'gateway' => self::GATEWAY_NAME,
            'webhook_data' => $webhookData,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    private function updateTransactionFromWebhook(string $providerTransactionId, string $status): void
    {
        $newStatus = match ($status) {
            'success' => 'completed',
            'failed' => 'failed',
            'pending' => 'processing',
            'cancelled' => 'failed',
            default => 'failed',
        };

        Transaction::where('gateway_transaction_id', $providerTransactionId)
            ->where('payment_gateway', self::GATEWAY_NAME)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        Log::info('Transaction updated from webhook', [
            'provider_transaction_id' => $providerTransactionId,
            'new_status' => $newStatus,
            'webhook_status' => $status,
        ]);
    }

    private function validateWebhookData(array $webhookData): ?array
    {
        $transactionId = $webhookData['transaction_id'] ?? null;
        $status = $webhookData['status'] ?? null;
        
        $validations = [
            fn() => !$transactionId ? [
                'success' => false,
                'message' => self::WEBHOOK_ERROR_MESSAGES['missing_transaction_id'],
                'error_type' => 'missing_transaction_id',
            ] : null,
            fn() => !$status ? [
                'success' => false,
                'message' => self::WEBHOOK_ERROR_MESSAGES['missing_status'],
                'error_type' => 'missing_status',
            ] : null,
            fn() => !in_array($status, ['success', 'failed', 'pending', 'cancelled']) ? [
                'success' => false,
                'message' => self::WEBHOOK_ERROR_MESSAGES['invalid_status'],
                'error_type' => 'invalid_status',
            ] : null,
            fn() => !is_string($transactionId) || empty($transactionId) ? [
                'success' => false,
                'message' => self::WEBHOOK_ERROR_MESSAGES['invalid_transaction_id_format'],
                'error_type' => 'invalid_transaction_id_format',
            ] : null,
        ];

        foreach ($validations as $validation) {
            $error = $validation();
            if ($error) {
                return $error;
            }
        }

        return null;
    }

    private function createErrorResponse(string $errorType, ?int $statusCode = null, ?string $responseBody = null): array
    {
        $statusCode = $statusCode ?? match ($errorType) {
            'invalid_currency' => 400,
            'invalid_amount' => 400,
            'connection_error' => 500,
            default => 500,
        };

        $responseBody = $responseBody ?? match ($errorType) {
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
}
