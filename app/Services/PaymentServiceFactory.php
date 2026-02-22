<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateways\EasyMoneyGateway;
use App\Services\PaymentGateways\SuperWalletzGateway;

class PaymentServiceFactory
{
    public static function create(string $gateway): PaymentService
    {
        return new PaymentService(
            match ($gateway) {
                'easy-money' => new EasyMoneyGateway(),
                'super-walletz' => new SuperWalletzGateway(),
                default => throw new \InvalidArgumentException("Unsupported gateway: {$gateway}"),
            }
        );
    }

    public static function createForWebhook(string $gateway): PaymentService
    {
        $webhookSupportedGateways = ['super-walletz'];
        
        if (!in_array($gateway, $webhookSupportedGateways)) {
            throw new \InvalidArgumentException("Gateway {$gateway} does not support webhooks");
        }

        return self::create($gateway);
    }

    public static function getSupportedGateways(): array
    {
        return ['easy-money', 'super-walletz'];
    }

    public static function getWebhookSupportedGateways(): array
    {
        return ['super-walletz'];
    }
}
