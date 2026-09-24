<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 50)->index();
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['order_id', 'gateway']);
            $table->index(['status', 'paid_at']);
        });

        $now = now();
        DB::table('orders')
            ->select(['id', 'payment_method', 'payment_status', 'total', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->each(function (object $order) use ($now): void {
                $status = match ($order->payment_status ?? 'unpaid') {
                    'paid', 'paid_refund_pending' => 'paid',
                    'refunded' => 'refunded',
                    default => 'pending',
                };

                DB::table('payment_transactions')->insert([
                    'order_id' => $order->id,
                    'gateway' => in_array($order->payment_method, ['cash', 'cod'], true) ? 'cod' : ($order->payment_method ?: 'other'),
                    'amount' => $order->total,
                    'status' => $status,
                    'paid_at' => $status === 'paid' ? ($order->updated_at ?: $now) : null,
                    'created_at' => $order->created_at ?: $now,
                    'updated_at' => $order->updated_at ?: $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
