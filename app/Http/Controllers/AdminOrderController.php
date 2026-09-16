<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\Orders\OrderCancellationService;
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

    public function __construct(private readonly OrderCancellationService $cancellationService) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        if (! in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $statusCounts = collect([
            '' => (int) Order::query()->whereNull('archived_at')->count(),
            'pending' => (int) Order::query()->whereNull('archived_at')->where('status', 'pending')->count(),
            'processing' => (int) Order::query()->whereNull('archived_at')->where('status', 'processing')->count(),
            'shipping' => (int) Order::query()->whereNull('archived_at')->where('status', 'shipping')->count(),
            'completed' => (int) Order::query()->whereNull('archived_at')->where('status', 'completed')->count(),
            'cancelled' => (int) Order::query()->whereNull('archived_at')->where('status', 'cancelled')->count(),
        ]);
        $orders = Order::with(['user', 'items.variant.product'])
            ->whereNull('archived_at')
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(in_array($paymentStatus, ['paid', 'paid_refund_pending', 'unpaid', 'refund_pending', 'refunded'], true), fn ($query) => $query->where('payment_status', $paymentStatus))
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
        if ($status === 'cancelled') {
            if ($order->status !== 'cancelled') {
                $this->cancellationService->cancel(
                    $order,
                    $order->cancellation_reason ?? 'Đơn hàng được hủy bởi quản trị viên.',
                    true
                );
            }

            return back()->with('status', 'Đã hủy đơn hàng theo quy trình vận hành.');
        }
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

    public function settleCancellation(Request $request, Order $order): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:stock_return,refund']])['action'];
        if ($action === 'stock_return') {
            $this->cancellationService->settleStockReturn($order);
            return back()->with('status', 'Đã xác nhận nhận lại hàng và hoàn tồn kho.');
        }

        $this->cancellationService->completeRefund($order);

        return back()->with('status', 'Đã xác nhận hoàn tiền cho khách hàng.');
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
            $lockedOrder->update(['archived_at' => now()]);
        });

        return redirect()->route('admin.orders.index')->with('status', 'Đã lưu trữ đơn hàng. Dữ liệu giao dịch vẫn được giữ lại.');
    }

    public function destroyAll(): RedirectResponse
    {
        $archivedCount = DB::transaction(fn (): int => Order::query()
            ->deletableByAdmin()
            ->update(['archived_at' => now()]));

        if ($archivedCount === 0) {
            return back()->with('status', 'Không có đơn hàng nào đủ điều kiện để lưu trữ.');
        }

        return redirect()->route('admin.orders.index')
            ->with('status', "Đã lưu trữ {$archivedCount} đơn hàng đủ điều kiện.");
    }
}
