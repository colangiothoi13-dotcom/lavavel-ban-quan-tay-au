<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['recipient_name', 'phone', 'address', 'payment_method', 'payment_status', 'status', 'cancellation_reason', 'completed_at', 'archived_at', 'total'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
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

    public function getPaymentLabelAttribute(): string
    {
        return $this->payment_method === 'bank_transfer' ? 'Chuyển khoản ngân hàng' : 'Tiền mặt khi nhận hàng';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return $this->payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán';
    }

    public function getStatusLabelAttribute(): string
    {
        return ['pending' => 'Chờ xác nhận', 'processing' => 'Đang xử lý', 'shipping' => 'Đang giao', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'][$this->status] ?? $this->status;
    }
}
