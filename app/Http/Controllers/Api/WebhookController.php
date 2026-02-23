<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\PaymentServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{

    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $paymentService = PaymentServiceFactory::createForWebhook($gateway);
            return $this->processWebhookRequest($request, $paymentService, $gateway);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported webhook gateway',
                'error' => $e->getMessage(),
                'supported_gateways' => $this->getWebhookSupportedGateways(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function processWebhookRequest(Request $request, PaymentService $paymentService, string $gateway): JsonResponse
    {
        $webhookData = $request->all();
        
        $validationError = $this->validateWebhookData($webhookData, $gateway);
        if ($validationError) {
            return $validationError;
        }

        $result = $paymentService->handleWebhook($webhookData);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'transaction_id' => $result['transaction_id'] ?? null,
                'status' => $result['status'] ?? null
            ],
        ], $result['success'] ? 200 : 400);
    }

    private function validateWebhookData(array $webhookData, string $gateway): ?JsonResponse
    {
        $requiredFields = match ($gateway) {
            'super-walletz' => ['transaction_id', 'status'],
            'easy-money' => [],
            default => [],
        };

        foreach ($requiredFields as $field) {
            if (!isset($webhookData[$field]) || empty($webhookData[$field])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook validation failed',
                    'errors' => [
                        $field => "The {$field} field is required."
                    ],
                ], 422);
            }
        }

        if ($gateway === 'super-walletz' && isset($webhookData['status'])) {
            $allowedStatuses = ['success', 'failed', 'pending', 'cancelled'];
            if (!in_array($webhookData['status'], $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook validation failed',
                    'errors' => [
                        'status' => "Invalid status. Allowed values: " . implode(', ', $allowedStatuses)
                    ],
                ], 422);
            }
        }

        return null;
    }

    private function getWebhookSupportedGateways(): array
    {
        return ['super-walletz'];
    }
}
