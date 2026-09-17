<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminOrderBulkActionService
{
    /**
     * @param  array<int, int>  $orderIds
     * @return Collection<int, Order>
     */
    public function loadSelected(array $orderIds): Collection
    {
        $orders = Order::query()
            ->with(['user', 'items.variant.product'])
            ->whereNull('archived_at')
            ->whereIn('id', $orderIds)
            ->get()
            ->keyBy('id');

        if ($orders->count() !== count($orderIds)) {
            throw ValidationException::withMessages([
                'order_ids' => 'Một hoặc nhiều đơn hàng không còn khả dụng để thao tác.',
            ]);
        }

        return collect($orderIds)
            ->map(fn (int $orderId): Order => $orders->get($orderId))
            ->values();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<int, mixed>>
     */
    public function exportRows(Collection $orders): array
    {
        $rows = [[
            'Mã đơn',
            'Ngày tạo',
            'Trạng thái',
            'Người nhận',
            'Số điện thoại',
            'Địa chỉ',
            'Sản phẩm',
            'Số lượng',
            'Tạm tính',
            'Phí vận chuyển',
            'Tổng tiền',
            'Thanh toán',
            'Mã GHN',
            'Đơn vị vận chuyển',
        ]];

        foreach ($orders as $order) {
            $subtotal = (float) $order->items->sum(fn ($item) => (float) $item->price * $item->quantity);
            $shippingFee = (float) ($order->shipping_fee ?? max(0, (float) $order->total - $subtotal));
            $products = $order->items->map(function ($item): string {
                $variant = trim((string) $item->variant_name);

                return $item->product_name
                    .($variant !== '' ? ' ('.$variant.')' : '')
                    .' × '.$item->quantity;
            })->implode(', ');

            $rows[] = [
                'DH'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                $order->created_at?->format('d/m/Y H:i'),
                $order->status_label,
                $order->recipient_name,
                $order->phone,
                $order->address,
                $products,
                (int) $order->items->sum('quantity'),
                $subtotal,
                $shippingFee,
                (float) $order->total,
                $order->payment_status_label,
                $order->ghn_order_code ?: '—',
                $order->shipping_provider === Order::SHIPPING_PROVIDER_GHN || $order->ghn_order_code ? 'GHN' : '—',
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{success: int, failed: int, skipped: int, messages: array<int, string>}
     */
    public function assignToGhn(Collection $orders): array
    {
        $summary = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'messages' => []];

        foreach ($orders as $order) {
            $label = 'DH'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

            if ($order->shipping_provider === Order::SHIPPING_PROVIDER_GHN || $order->ghn_order_code) {
                if (! $order->shipping_provider && $order->ghn_order_code) {
                    $order->update(['shipping_provider' => Order::SHIPPING_PROVIDER_GHN]);
                }

                $summary['skipped']++;
                $summary['messages'][] = $label.': đơn đã được gán GHN.';
                continue;
            }

            if (in_array($order->status, ['cancelled', 'completed'], true)) {
                $summary['skipped']++;
                $summary['messages'][] = $label.': trạng thái đơn không thể gán GHN.';
                continue;
            }

            $order->update(['shipping_provider' => Order::SHIPPING_PROVIDER_GHN]);
            $summary['success']++;
        }

        return $summary;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{archived: int, skipped: int, messages: array<int, string>}
     */
    public function archive(Collection $orders): array
    {
        return DB::transaction(function () use ($orders): array {
            $summary = ['archived' => 0, 'skipped' => 0, 'messages' => []];

            foreach ($orders as $order) {
                $lockedOrder = Order::query()->lockForUpdate()->find($order->getKey());
                $label = 'DH'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

                if (! $lockedOrder || ! $lockedOrder->canBeDeletedByAdmin()) {
                    $summary['skipped']++;
                    $summary['messages'][] = $label.': chưa đủ điều kiện lưu trữ.';
                    continue;
                }

                $lockedOrder->update(['archived_at' => now()]);
                $summary['archived']++;
            }

            return $summary;
        });
    }

}
