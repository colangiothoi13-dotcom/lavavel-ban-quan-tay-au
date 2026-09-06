<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\MomoPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MomoPaymentController
{
    public function __construct(private readonly MomoPaymentService $momoPaymentService) {}

    public function start(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->id === $order->user_id, 403);
        $this->assertMomoOrder($order);
        abort_if(
            in_array($order->status, ['completed', 'cancelled'], true),
            422,
            'Đơn hàng này không thể tạo link MoMo.'
        );
        abort_if($order->payment_status === 'paid', 422, 'Đơn đã thanh toán rồi.');

        $response = $this->momoPaymentService->createPayment(
            $order,
            route('momo.result'),
            route('momo.ipn')
        );

        return redirect()->away($response['pay_url']);
    }

    public function result(Request $request): View|RedirectResponse
    {
        $summary = $this->momoPaymentService->finalizeFromReturn($request->query());
        $momoOrderId = $summary['momo_order_id'] ?? null;
        if ($momoOrderId === null) {
            return redirect()->route('user.orders.index')->withErrors(['momo' => $summary['message'] ?? 'Không thể xác định đơn hàng MoMo.']);
        }

        $order = $this->findOrderByMomoReference($momoOrderId);
        if (! $order) {
            return redirect()->route('user.orders.index')->withErrors(['momo' => 'Không tìm thấy đơn hàng tương ứng.']);
        }

        $requestUser = $request->user();
        if ($requestUser && $requestUser->id !== $order->user_id) {
            abort(403);
        }

        if ($summary['status'] === 'success'
            && $order->status !== 'cancelled'
            && $order->payment_status !== 'paid'
            && (! $order->payment_expires_at || $order->payment_expires_at->isFuture())) {
            $order->update([
                'payment_status' => 'paid',
                'payment_method' => Order::PAYMENT_METHOD_MOMO,
            ]);
        }

        return view('orders.momo-result', [
            'order' => $order,
            'status' => $summary['status'],
            'message' => $summary['message'],
        ]);
    }

    public function ipn(Request $request): Response
    {
        $summary = $this->momoPaymentService->finalizeFromIpn($request->all());
        if ($summary['status'] === 'success') {
            $order = $this->findOrderByMomoReference($summary['momo_order_id'] ?? '');
            if ($order) {
                DB::transaction(function () use ($order): void {
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                    if ($lockedOrder->status !== 'cancelled'
                        && $lockedOrder->payment_status !== 'paid'
                        && (! $lockedOrder->payment_expires_at || $lockedOrder->payment_expires_at->isFuture())) {
                        $lockedOrder->update(['payment_status' => 'paid']);
                    }
                });
            }
        }

        return response('OK', 200);
    }

    private function assertMomoOrder(Order $order): void
    {
        abort_if($order->payment_method !== Order::PAYMENT_METHOD_MOMO, 422, 'Đây không phải đơn MoMo.');
        abort_if(! $this->momoPaymentService->isConfigured(), 503, 'Tính năng MoMo chưa được cấu hình.');
    }

    private function findOrderByMomoReference(string $momoOrderId): ?Order
    {
        $orderId = $this->momoPaymentService->extractOrderIdFromReference($momoOrderId);
        if (! $orderId) {
            return null;
        }

        return Order::query()
            ->where('id', $orderId)
            ->where('payment_method', Order::PAYMENT_METHOD_MOMO)
            ->first();
    }
}
