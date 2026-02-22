<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'amount',
        'currency',
        'status',
        'payment_gateway',
        'gateway_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function paymentRquests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}
