<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->order->status === 'shipping'
            ? "Đơn quần tây #{$this->order->id} của bạn đã được đóng gói và giao cho đơn vị vận chuyển."
            : "Đơn quần tây #{$this->order->id} của bạn đã được giao thành công và hoàn thành.";

        return (new MailMessage)
            ->subject("Cập nhật đơn hàng #{$this->order->id}")
            ->greeting('Xin chào '.$notifiable->name.'!')
            ->line($message)
            ->line('Tổng đơn hàng: '.number_format((float) $this->order->total, 0, ',', '.').' đ')
            ->action('Xem chi tiết đơn hàng', route('user.orders.show', $this->order))
            ->line('Cảm ơn bạn đã mua sắm tại Quần Tây Âu.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
        ];
    }
}
