<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'shipping', 'completed', 'cancelled'];
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['pending', 'processing', 'cancelled'],
        'processing' => ['processing', 'shipping', 'cancelled'],
        'shipping' => ['shipping', 'completed', 'cancelled'],
        'completed' => ['completed'],
        'cancelled' => ['cancelled'],
    ];

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        if (! in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $statusCounts = collect([
            '' => (int) Order::query()->count(),
            'pending' => (int) Order::query()->where('status', 'pending')->count(),
            'processing' => (int) Order::query()->where('status', 'processing')->count(),
            'shipping' => (int) Order::query()->where('status', 'shipping')->count(),
            'completed' => (int) Order::query()->where('status', 'completed')->count(),
            'cancelled' => (int) Order::query()->where('status', 'cancelled')->count(),
        ]);
        $orders = Order::with(['user', 'items.variant.product'])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(in_array($paymentStatus, ['paid', 'unpaid'], true), fn ($query) => $query->where('payment_status', $paymentStatus))
            ->activeFirst()
            ->get();
        $pendingCount = Order::query()->visibleInOrderHistory()->where('status', 'pending')->count();
        $deletableCount = Order::query()->deletableByAdmin()->count();

        return view('admin.orders.index', compact(
            'orders',
            'status',
            'statusCounts',
            'paymentStatus',
            'pendingCount',
            'deletableCount'
        ));
    }

    public function confirmAll(): RedirectResponse
    {
        $confirmedCount = Order::query()
            ->visibleInOrderHistory()
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
        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $status, &$previousStatus): void {
            // Khóa đơn hàng để hai yêu cầu hủy cùng lúc không thể
            // cộng trả tồn kho hai lần.
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $previousStatus = $lockedOrder->status;

            abort_unless(
                in_array($status, self::ALLOWED_TRANSITIONS[$previousStatus] ?? [], true),
                422,
                'Trạng thái đơn hàng không thể chuyển theo luồng hiện tại.'
            );

            $shouldFallbackToCash = $status === 'shipping' && $lockedOrder->canAutoFallbackToCash();

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

            $lockedOrder->update([
                    'status' => $status,
                    'cancellation_reason' => $status === 'cancelled'
                        ? ($lockedOrder->cancellation_reason ?? 'Đơn hàng được hủy bởi quản trị viên.')
                        : null,
                    'completed_at' => $status === 'completed'
                        ? ($lockedOrder->completed_at ?? now())
                        : null,
                    'payment_method' => $shouldFallbackToCash
                        ? Order::PAYMENT_METHOD_CASH
                        : $lockedOrder->payment_method,
                    'payment_reference' => $shouldFallbackToCash
                        ? null
                        : $lockedOrder->payment_reference,
                    'momo_order_id' => $shouldFallbackToCash
                        ? null
                        : $lockedOrder->momo_order_id,
                ]);
        });

        if ($previousStatus !== $status && in_array($status, ['shipping', 'completed'], true)) {
            OrderStatusChanged::dispatch($order->fresh(), $previousStatus);
        }

        return back()->with('status', 'Đã cập nhật trạng thái đơn hàng.');
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        if ($order->isMomoOrder()) {
            abort(422, 'Không cho phép thay đổi thủ công cho đơn MoMo.');
        }

        $paymentStatus = $request->validate(['payment_status' => ['required', 'in:paid,unpaid']])['payment_status'];
        $order->update(['payment_status' => $paymentStatus]);

        return back()->with('status', 'Đã cập nhật trạng thái thanh toán.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            abort_unless(
                $lockedOrder->canBeDeletedByAdmin(),
                422,
                'Chỉ có thể xóa đơn đã hủy hoặc đơn đã hoàn thành đủ 7 ngày.'
            );
            $lockedOrder->delete();
        });

        return redirect()->route('admin.orders.index')->with('status', 'Đã xóa đơn hàng khỏi hệ thống.');
    }

    public function destroyAll(): RedirectResponse
    {
        $deletedCount = DB::transaction(fn (): int => Order::query()->deletableByAdmin()->delete());

        if ($deletedCount === 0) {
            return back()->with('status', 'Không có đơn hàng nào đủ điều kiện để xóa.');
        }

        return redirect()->route('admin.orders.index')
            ->with('status', "Đã xóa {$deletedCount} đơn hàng đủ điều kiện.");
    }
}
