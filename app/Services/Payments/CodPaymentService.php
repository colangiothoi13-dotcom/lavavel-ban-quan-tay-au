<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

class CodPaymentService
{
    public function update(Order $order, string $paymentStatus): void
    {
        DB::transaction(function () use ($order, $paymentStatus): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            abort_unless($lockedOrder->isCashPayment(), 422, 'Chỉ có thể cập nhật thanh toán COD cho đơn tiền mặt.');

            if ($paymentStatus === 'paid') {
                $paidTransaction = PaymentTransaction::query()
                    ->where('order_id', $lockedOrder->getKey())
                    ->where('gateway', 'cod')
                    ->where('status', PaymentTransaction::STATUS_PAID)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $paidTransaction) {
                    $pendingTransaction = PaymentTransaction::query()
                        ->where('order_id', $lockedOrder->getKey())
                        ->where('gateway', 'cod')
                        ->where('status', PaymentTransaction::STATUS_PENDING)
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    ($pendingTransaction ?: new PaymentTransaction)->fill([
                        'order_id' => $lockedOrder->getKey(),
                        'gateway' => 'cod',
                        'amount' => $lockedOrder->total,
                        'status' => PaymentTransaction::STATUS_PAID,
                        'paid_at' => now(),
                    ])->save();
                }
            } else {
                PaymentTransaction::query()
                    ->where('order_id', $lockedOrder->getKey())
                    ->where('gateway', 'cod')
                    ->where('status', PaymentTransaction::STATUS_PAID)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first()?->update(['status' => PaymentTransaction::STATUS_CANCELLED]);

                $hasPendingTransaction = PaymentTransaction::query()
                    ->where('order_id', $lockedOrder->getKey())
                    ->where('gateway', 'cod')
                    ->where('status', PaymentTransaction::STATUS_PENDING)
                    ->exists();

                if (! $hasPendingTransaction) {
                    $lockedOrder->paymentTransactions()->create([
                        'gateway' => 'cod',
                        'amount' => $lockedOrder->total,
                        'status' => PaymentTransaction::STATUS_PENDING,
                    ]);
                }
            }

            $lockedOrder->update(['payment_status' => $paymentStatus]);
        });
    }
}
