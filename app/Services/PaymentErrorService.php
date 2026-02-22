<?php

namespace App\Services;

class PaymentErrorService
{
    public static function createErrorResponse(
        int $statusCode,
        string $responseMessage,
        string $errorType,
        string $errorMessage
    ): array {
        return [
            'status_code' => $statusCode,
            'response_message' => $responseMessage,
            'success' => false,
            'error' => $errorMessage,
            'error_type' => $errorType,
        ];
    }

    public static function createSuccessResponse(
        int $statusCode,
        string $responseMessage,
        array $additionalData = []
    ): array {
        return [
            'status_code' => $statusCode,
            'response_message' => $responseMessage,
            'success' => true,
            ...$additionalData
        ];
    }
}
