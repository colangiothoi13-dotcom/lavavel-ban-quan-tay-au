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
        'payment_status',
        'payment_expires_at',
        'status',
        'cancellation_reason',
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

    public function scopeDeletableByAdmin(Builder $query): Builder
    {
        $cutoff = now()->subDays(7);

        return $query->where(function (Builder $deletableQuery) use ($cutoff): void {
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
        if ($this->status === 'cancelled') {
            return true;
        }

        $completedDate = $this->completed_at ?? $this->updated_at;

        return $this->status === 'completed'
            && $completedDate !== null
            && $completedDate->lte(now()->subDays(7));
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
        return $this->payment_status === 'paid' ? 'Da thanh toan' : 'Chua thanh toan';
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
            && $this->payment_status !== 'paid'
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
        return $this->payment_status !== 'paid' && ! $this->isCashPayment();
    }
}
