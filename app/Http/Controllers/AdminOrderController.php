<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'shipping', 'completed', 'cancelled'];

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        $orders = Order::with(['user', 'items'])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(in_array($paymentStatus, ['paid', 'unpaid'], true), fn ($query) => $query->where('payment_status', $paymentStatus))
            ->latest()
            ->get();
        $pendingCount = Order::query()->where('status', 'pending')->count();

        return view('admin.orders.index', compact('orders', 'status', 'paymentStatus', 'pendingCount'));
    }

    public function confirmAll(): RedirectResponse
    {
        $confirmedCount = Order::query()
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($confirmedCount === 0) {
            return back()->with('status', 'Không có đơn hàng nào đang chờ xác nhận.');
        }

        return back()->with('status', "Đã xác nhận {$confirmedCount} đơn hàng.");
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:pending,processing,shipping,completed,cancelled']])['status'];

        DB::transaction(function () use ($order, $status): void {
            // Khóa đơn hàng để hai yêu cầu hủy cùng lúc không thể
            // cộng trả tồn kho hai lần.
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($status === 'cancelled' && $lockedOrder->status !== 'cancelled') {
                $lockedOrder->items()
                    ->whereNotNull('product_variant_id')
                    ->get(['product_variant_id', 'quantity'])
                    ->each(function ($item): void {
                        ProductVariant::query()
                            ->whereKey($item->product_variant_id)
                            ->increment('stock', $item->quantity);
                    });
            }

            $lockedOrder->update(['status' => $status]);
        });

        return back()->with('status', 'Đã cập nhật trạng thái đơn hàng.');
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $paymentStatus = $request->validate(['payment_status' => ['required', 'in:paid,unpaid']])['payment_status'];
        $order->update(['payment_status' => $paymentStatus]);

        return back()->with('status', 'Đã cập nhật trạng thái thanh toán.');
    }
}
