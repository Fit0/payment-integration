<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\WebhookLog;
use App\Services\PaymentErrorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    private const STATUS_SUCCESS = 'success';
    private const STATUS_PENDING = 'pending';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';

    public function __construct(
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function processPayment(float $amount, string $currency, array $additionalData = []): array
    {
        return DB::transaction(function () use ($amount, $currency, $additionalData) {
            try {
                $transaction = $this->createTransaction($amount, $currency);
                $gatewayResponse = $this->paymentGateway->processPayment($amount, $currency, $additionalData);
                $this->logPaymentRequest($transaction->id, $amount, $currency, $additionalData, $gatewayResponse);

                $this->updateTransactionStatus($transaction, $gatewayResponse);

                return [
                    'success' => $gatewayResponse['success'] ?? false,
                    'transaction_id' => $transaction->id,
                    'status' => $transaction->status,
                    'gateway_response' => $gatewayResponse,
                ];
            } catch (Exception $e) {
                $this->logPaymentError($e, [
                    'amount' => $amount,
                    'currency' => $currency,
                ]);

                throw $e;
            }
        });
    }

    public function handleWebhook(array $webhookData): array
    {
        try {
            $gatewayResponse = $this->paymentGateway->handleWebhook($webhookData);

            if (!$gatewayResponse['success']) {
                return $gatewayResponse;
            }

            $transaction = Transaction::where('gateway_transaction_id', $gatewayResponse['transaction_id'])
                ->where('payment_gateway', $this->paymentGateway->getGatewayName())
                ->first();

            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction not found',
                ];
            }

            $this->logWebhook($transaction->id, $webhookData);

            $newStatus = $gatewayResponse['status'] === self::STATUS_SUCCESS ? self::STATUS_COMPLETED : self::STATUS_FAILED;
            $this->updateTransactionStatus($transaction, $newStatus);

            return [
                'success' => true,
                'message' => 'Webhook processed successfully',
                'transaction_id' => $transaction->id,
                'status' => $newStatus,
            ];
        } catch (Exception $e) {
            $this->logPaymentError($e, [
                'webhook_data' => $webhookData,
            ]);

            return PaymentErrorService::createErrorResponse(
                500,
                'Webhook processing failed',
                'webhook_processing_error',
                'Error processing webhook'
            );
        }
    }

    private function logPaymentRequest(int $transactionId, float $amount, string $currency, array $additionalData, array $gatewayResponse): void
    {
        PaymentRequest::create([
            'transaction_id' => $transactionId,
            'payment_gateway' => $this->paymentGateway->getGatewayName(),
            'request_data' => [
                'amount' => $amount,
                'currency' => $currency,
                ...$additionalData,
            ],
            'response_data' => $gatewayResponse,
            'status_code' => $gatewayResponse['status_code'] ?? null,
            'response_message' => $gatewayResponse['response_message'] ?? null,
        ]);
    }

    private function logWebhook(int $transactionId, array $webhookData): void
    {
        WebhookLog::create([
            'transaction_id' => $transactionId,
            'payment_gateway' => $this->paymentGateway->getGatewayName(),
            'webhook_data' => $webhookData,
            'processed_at' => now(),
        ]);
    }

    private function createTransaction(float $amount, string $currency): Transaction
    {
        return Transaction::create([
            'amount' => $amount,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
            'payment_gateway' => $this->paymentGateway->getGatewayName(),
            'gateway_transaction_id' => null,
        ]);
    }

    private function updateTransactionStatus(Transaction $transaction, array|string $gatewayResponseOrStatus): void
    {
        if (is_array($gatewayResponseOrStatus) && $gatewayResponseOrStatus[self::STATUS_SUCCESS]) {
            $transaction->update([
                'status' => self::STATUS_COMPLETED,
                'gateway_transaction_id' => $gatewayResponseOrStatus['transaction_id'] ?? null,
            ]);
        } else {
            $transaction->update([
                'status' => is_array($gatewayResponseOrStatus) ? self::STATUS_FAILED : $gatewayResponseOrStatus,
            ]);
        }
    }

    private function logPaymentError(Exception $e, array $additionalContext = []): void
    {
        Log::error('Payment processing error', [
            'error' => $e->getMessage(),
            'gateway' => $this->paymentGateway->getGatewayName(),
            ...$additionalContext,
        ]);
    }
}
