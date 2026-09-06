<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:archive-expired', function () {
    $archived = \App\Models\Order::query()
        ->readyForArchival()
        ->where(function ($query): void {
            $cutoff = now()->subDays(2);
            $query
                ->where(function ($completedQuery) use ($cutoff): void {
                    $completedQuery
                        ->where('status', 'completed')
                        ->where(function ($dateQuery) use ($cutoff): void {
                            $dateQuery
                                ->where('completed_at', '<=', $cutoff)
                                ->orWhere(function ($legacyQuery) use ($cutoff): void {
                                    $legacyQuery->whereNull('completed_at')->where('updated_at', '<=', $cutoff);
                                });
                        });
                })
                ->orWhere(function ($cancelledQuery) use ($cutoff): void {
                    $cancelledQuery->where('status', 'cancelled')->where('updated_at', '<=', $cutoff);
                });
        })
        ->update(['archived_at' => now()]);

    $this->info("Đã lưu trữ {$archived} đơn hàng hoàn thành hoặc đã hủy quá 2 ngày.");
})->purpose('Ẩn đơn hàng hoàn thành hoặc đã hủy khỏi lịch sử sau 2 ngày');

Schedule::command('orders:archive-expired')->hourly()->withoutOverlapping();

Artisan::command('orders:cancel-expired-payments', function () {
    $cancelled = 0;
    \App\Models\Order::query()
        ->where('payment_method', \App\Models\Order::PAYMENT_METHOD_MOMO)
        ->where('payment_status', 'unpaid')
        ->whereIn('status', ['pending', 'processing'])
        ->whereNotNull('payment_expires_at')
        ->where('payment_expires_at', '<=', now())
        ->pluck('id')
        ->each(function (int $orderId) use (&$cancelled): void {
            DB::transaction(function () use ($orderId, &$cancelled): void {
                $order = \App\Models\Order::query()->lockForUpdate()->find($orderId);
                if (! $order
                    || $order->payment_method !== \App\Models\Order::PAYMENT_METHOD_MOMO
                    || $order->payment_status !== 'unpaid'
                    || ! in_array($order->status, ['pending', 'processing'], true)
                    || ! $order->payment_expires_at
                    || $order->payment_expires_at->isFuture()) {
                    return;
                }

                $order->items()
                    ->whereNotNull('product_variant_id')
                    ->get(['product_variant_id', 'quantity'])
                    ->each(function ($item): void {
                        \App\Models\ProductVariant::query()
                            ->whereKey($item->product_variant_id)
                            ->increment('stock', $item->quantity);
                    });
                $order->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Đơn MoMo hết hạn thanh toán.',
                ]);
                $cancelled++;
            });
        });

    $this->info("Đã hủy {$cancelled} đơn MoMo hết hạn thanh toán.");
})->purpose('Hủy đơn MoMo chưa thanh toán và hoàn tồn kho khi hết hạn');

Schedule::command('orders:cancel-expired-payments')->everyFiveMinutes()->withoutOverlapping();
