<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_COD = 'cod';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_MOMO = 'momo';

    protected $fillable = [
        'recipient_name',
        'phone',
        'address',
        'payment_method',
        'payment_reference',
        'momo_order_id',
        'momo_request_id',
        'momo_transaction_id',
        'momo_response_time',
        'payment_status',
        'payment_expires_at',
        'status',
        'cancellation_reason',
        'stock_return_status',
        'completed_at',
        'archived_at',
        'total',
        'shipping_fee',
        'ghn_district_id',
        'ghn_ward_code',
        'ghn_order_code',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'payment_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function momoPaymentAttempts(): HasMany
    {
        return $this->hasMany(MomoPaymentAttempt::class);
    }

    public function scopeVisibleInOrderHistory(Builder $query): Builder
    {
        $cutoff = now()->subDays(2);

        return $query
            ->whereNull('archived_at')
            ->where(function (Builder $visibilityQuery) use ($cutoff): void {
                $visibilityQuery
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->orWhere(function (Builder $completedQuery) use ($cutoff): void {
                        $completedQuery
                            ->where('status', 'completed')
                            ->where(function (Builder $dateQuery) use ($cutoff): void {
                                $dateQuery
                                    ->where('completed_at', '>', $cutoff)
                                    ->orWhere(function (Builder $legacyQuery) use ($cutoff): void {
                                        $legacyQuery->whereNull('completed_at')->where('updated_at', '>', $cutoff);
                                    });
                            });
                    })
                    ->orWhere(function (Builder $cancelledQuery) use ($cutoff): void {
                        $cancelledQuery->where('status', 'cancelled')->where('updated_at', '>', $cutoff);
                    });
            });
    }

    public function scopeActiveFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function scopeReadyForArchival(Builder $query): Builder
    {
        return $query->whereNull('archived_at')
            ->whereNotIn('payment_status', ['refund_pending', 'paid_refund_pending'])
            ->where('stock_return_status', '!=', 'pending_return')
            ->whereDoesntHave('momoPaymentAttempts', fn (Builder $attempts) => $attempts->where('status', 'refund_pending'));
    }

    public function scopeDeletableByAdmin(Builder $query): Builder
    {
        $cutoff = now()->subDays(7);

        return $query
            ->readyForArchival()
            ->where(function (Builder $deletableQuery) use ($cutoff): void {
            $deletableQuery
                ->where('status', 'cancelled')
                ->orWhere(function (Builder $completedQuery) use ($cutoff): void {
                    $completedQuery
                        ->where('status', 'completed')
                        ->where(function (Builder $dateQuery) use ($cutoff): void {
                            $dateQuery
                                ->where('completed_at', '<=', $cutoff)
                                ->orWhere(function (Builder $legacyQuery) use ($cutoff): void {
                                    $legacyQuery->whereNull('completed_at')->where('updated_at', '<=', $cutoff);
                                });
                        });
                });
        });
    }

    public function canBeDeletedByAdmin(): bool
    {
        return $this->exists && static::query()->whereKey($this->getKey())->deletableByAdmin()->exists();
    }

    public function getPaymentLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_METHOD_COD => 'COD / Tiền mặt khi nhận hàng',
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyen khoan ngan hang',
            self::PAYMENT_METHOD_MOMO => 'MoMo',
            default => 'Tien mat khi nhan hang',
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Đã thanh toán',
            'paid_refund_pending' => 'Đã thanh toán, chờ hoàn tiền thừa',
            'refund_pending' => 'Chờ hoàn tiền',
            'refunded' => 'Đã hoàn tiền',
            default => 'Chưa thanh toán',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'pending' => 'Cho xac nhan',
            'processing' => 'Dang xu ly',
            'shipping' => 'Dang giao',
            'completed' => 'Hoan thanh',
            'cancelled' => 'Da huy',
        ][$this->status] ?? $this->status;
    }

    public function getCanRetryMomoPaymentAttribute(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_MOMO
            && $this->payment_status === 'unpaid'
            && in_array($this->status, ['pending', 'processing', 'shipping'], true);
    }

    public function isMomoOrder(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_MOMO;
    }

    public function isCashPayment(): bool
    {
        return in_array($this->payment_method, [self::PAYMENT_METHOD_CASH, self::PAYMENT_METHOD_COD], true);
    }

    public function canAutoFallbackToCash(): bool
    {
        return $this->payment_status === 'unpaid' && ! $this->isCashPayment();
    }

    public function syncPaymentStatusFromAttempts(): string
    {
        $statuses = $this->momoPaymentAttempts()->pluck('status');
        $hasPaid = $statuses->contains('paid')
            || (! $this->isMomoOrder() && in_array($this->payment_status, ['paid', 'paid_refund_pending'], true));
        $hasRefundPending = $statuses->contains('refund_pending');

        $status = match (true) {
            $hasPaid && $hasRefundPending => 'paid_refund_pending',
            $hasPaid => 'paid',
            $hasRefundPending => 'refund_pending',
            $statuses->contains('refunded') => 'refunded',
            default => $this->payment_status,
        };

        if ($this->payment_status !== $status) {
            $this->update(['payment_status' => $status]);
        }

        return $status;
    }
}
