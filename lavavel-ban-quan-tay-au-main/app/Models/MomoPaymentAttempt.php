<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomoPaymentAttempt extends Model
{
    protected $fillable = [
        'order_id',
        'momo_order_id',
        'request_id',
        'amount',
        'status',
        'transaction_id',
        'response_time',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'response_time' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
