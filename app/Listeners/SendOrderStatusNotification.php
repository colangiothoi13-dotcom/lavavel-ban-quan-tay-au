<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderStatusNotification
{
    public function handle(OrderStatusChanged $event): void
    {
        try {
            $event->order->user?->notify(
                new OrderStatusUpdatedNotification($event->order)
            );
        } catch (Throwable $exception) {
            // Lỗi SMTP không được làm rollback trạng thái đơn hàng đã cập nhật.
            Log::error('Không thể gửi thông báo trạng thái đơn hàng.', [
                'order_id' => $event->order->id,
                'status' => $event->order->status,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
