<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'transaction_id',
        'payment_gateway',
        'webhook_data',
        'processed_at',
    ];

    protected $casts = [
        'webhook_data' => 'json',
        'processed_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
