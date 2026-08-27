<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['recipient_name', 'phone', 'address', 'payment_method', 'payment_status', 'status', 'total'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
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
