<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderCancellationService
{
    public function cancel(Order $order, string $reason, bool $allowShipping): bool
    {
        return DB::transaction(function () use ($order, $reason, $allowShipping): bool {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $allowedStatuses = $allowShipping
                ? ['pending', 'processing', 'shipping']
                : ['pending', 'processing'];

            if (! in_array($lockedOrder->status, $allowedStatuses, true)) {
                throw new HttpException(422, $allowShipping
                    ? 'Không thể hủy đơn đã hoàn thành hoặc đã hủy.'
                    : 'Đơn đang giao hoặc đã kết thúc cần được xử lý qua quy trình hoàn hàng.');
            }

            $stockReturnStatus = $lockedOrder->status === 'shipping'
                ? 'pending_return'
                : 'returned';
            if ($stockReturnStatus === 'returned') {
                $this->returnStock($lockedOrder);
            }

            $requiresRefund = in_array($lockedOrder->payment_status, ['paid', 'paid_refund_pending'], true);
            if ($requiresRefund) {
                $lockedOrder->momoPaymentAttempts()
                    ->whereIn('status', ['paid', 'refund_pending'])
                    ->update(['status' => 'refund_pending']);
            }
            $lockedOrder->update([
                'status' => 'cancelled',
                'stock_return_status' => $stockReturnStatus,
                'payment_status' => $requiresRefund
                    ? 'refund_pending'
                    : $lockedOrder->payment_status,
                'cancellation_reason' => trim($reason),
            ]);
            return $requiresRefund;
        });
    }

    public function settleStockReturn(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->status !== 'cancelled' || $lockedOrder->stock_return_status !== 'pending_return') {
                throw new HttpException(422, 'Đơn này không chờ xác nhận nhận lại hàng.');
            }

            $this->returnStock($lockedOrder);
            $lockedOrder->update(['stock_return_status' => 'returned']);
        });
    }

    public function completeRefund(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if (! in_array($lockedOrder->payment_status, ['refund_pending', 'paid_refund_pending'], true)) {
                throw new HttpException(422, 'Đơn này không chờ hoàn tiền.');
            }

            $lockedOrder->momoPaymentAttempts()
                ->where('status', 'refund_pending')
                ->update(['status' => 'refunded']);
            if ($lockedOrder->payment_status === 'refund_pending'
                && (! $lockedOrder->isMomoOrder() || ! $lockedOrder->momoPaymentAttempts()->exists())) {
                $lockedOrder->update(['payment_status' => 'refunded']);
            } else {
                $lockedOrder->syncPaymentStatusFromAttempts();
            }
        });
    }

    public function cancelExpiredPayment(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->status !== 'cancelled'
                && in_array($lockedOrder->status, ['pending', 'processing'], true)
                && $lockedOrder->payment_status === 'refund_pending') {
                $this->returnStock($lockedOrder);
                $lockedOrder->update([
                    'status' => 'cancelled',
                    'stock_return_status' => 'returned',
                    'cancellation_reason' => 'Đơn MoMo hết hạn thanh toán; giao dịch cần hoàn tiền.',
                ]);
            }
        });
    }

    private function returnStock(Order $order): void
    {
        $order->items()
            ->whereNotNull('product_variant_id')
            ->get(['product_variant_id', 'quantity'])
            ->each(function ($item): void {
                ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->increment('stock', $item->quantity);
            });
    }
}
