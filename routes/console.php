<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:archive-expired', function () {
    $archived = \App\Models\Order::query()
        ->whereNull('archived_at')
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
