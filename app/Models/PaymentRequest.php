<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    protected $fillable = [
        'transaction_id',
        'payment_gateway',
        'request_data',
        'response_data',
        'status_code',
        'response_message',
    ];

    protected $casts = [
        'request_data' => 'json',
        'response_data' => 'json',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
