<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\PaymentServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function processPayment(Request $request, string $gateway): JsonResponse
    {
        try {
            $paymentService = PaymentServiceFactory::create($gateway);
            return $this->processPaymentRequest($request, $paymentService, $gateway);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported payment gateway',
                'error' => $e->getMessage(),
                'supported_gateways' => PaymentServiceFactory::getSupportedGateways(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function processPaymentRequest(Request $request, PaymentService $paymentService, string $gateway): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->getValidationRules($gateway));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        
        $result = $paymentService->processPayment(
            (float) $validated['amount'],
            $validated['currency'],
            $this->getAdditionalData($validated, $gateway)
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $this->getSuccessMessage($gateway, $result['success']),
            'data' => [
                'transaction_id' => $result['transaction_id'],
                'status' => $result['status'],
                'gateway_response' => $result['gateway_response'],
                // Incluir metadata adicional si el gateway la proporciona
                ...($result['webhook_received'] ?? null ? [
                    'webhook_received' => $result['webhook_received'],
                    'processing_time' => $result['processing_time'] ?? 0,
                    'gateway_transaction_id' => $result['gateway_transaction_id'] ?? null,
                ] : []),
            ],
        ], $result['success'] ? 200 : 400);
    }

    private function getValidationRules(string $gateway): array
    {
        $commonRules = [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
        ];

        return match ($gateway) {
            'easy-money' => $commonRules,
            'super-walletz' => [...$commonRules, 'callback_url' => 'required|url'],
            default => $commonRules,
        };
    }

    private function getAdditionalData(array $validated, string $gateway): array
    {
        return match ($gateway) {
            'super-walletz' => ['callback_url' => $validated['callback_url']],
            default => [],
        };
    }

    private function getSuccessMessage(string $gateway, bool $success): string
    {
        if (!$success) {
            return 'Payment failed';
        }

        return match ($gateway) {
            'easy-money' => 'Payment processed successfully',
            'super-walletz' => 'Payment initiated successfully',
            default => 'Payment processed successfully',
        };
    }
}
