<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\MomoPaymentAttempt;
use App\Services\Payments\MomoPaymentService;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MomoPaymentController
{
    public function __construct(
        private readonly MomoPaymentService $momoPaymentService,
        private readonly OrderCancellationService $cancellationService
    ) {}

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
        abort_unless($order->payment_status === 'unpaid', 422, 'Đơn này không thể tiếp tục thanh toán MoMo.');

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

        $attempt = $this->findPaymentAttempt($momoOrderId);
        if (! $attempt) {
            return redirect()->route('user.orders.index')->withErrors(['momo' => 'Không tìm thấy đơn hàng tương ứng.']);
        }
        $order = $attempt->order;

        $requestUser = $request->user();
        if ($requestUser && $requestUser->id !== $order->user_id) {
            abort(403);
        }

        if ($summary['status'] === 'success') {
            $summary['status'] = $this->applySuccessfulCallback($attempt, $summary['payload']);
            if ($summary['status'] === 'invalid') {
                return redirect()->route('user.orders.index')->withErrors(['momo' => 'Thông tin giao dịch MoMo không khớp với đơn hàng.']);
            }
            $order = $order->fresh();
            $summary['message'] = match ($summary['status']) {
                'refund_pending' => 'Giao dịch đang chờ hoàn tiền.',
                'paid_refund_pending' => 'Đơn đã được thanh toán; khoản thanh toán thừa đang chờ hoàn tiền.',
                'refunded' => 'Giao dịch đã được xác nhận hoàn tiền.',
                default => $summary['message'],
            };
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
            $attempt = $this->findPaymentAttempt($summary['momo_order_id'] ?? '');
            if ($attempt) {
                $this->applySuccessfulCallback($attempt, $summary['payload']);
            }
        }

        return response()->noContent();
    }

    private function assertMomoOrder(Order $order): void
    {
        abort_if($order->payment_method !== Order::PAYMENT_METHOD_MOMO, 422, 'Đây không phải đơn MoMo.');
        abort_if(! $this->momoPaymentService->isConfigured(), 503, 'Tính năng MoMo chưa được cấu hình.');
    }

    private function findPaymentAttempt(string $momoOrderId): ?MomoPaymentAttempt
    {
        return MomoPaymentAttempt::query()
            ->with('order')
            ->where('momo_order_id', $momoOrderId)
            ->first();
    }

    private function applySuccessfulCallback(MomoPaymentAttempt $attempt, array $payload): string
    {
        return DB::transaction(function () use ($attempt, $payload): string {
            // Use the same lock order as cancellation and refund settlement.
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($attempt->order_id);
            $lockedAttempt = MomoPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! $this->momoPaymentService->callbackMatchesAttempt($lockedAttempt, $payload)) {
                return 'invalid';
            }

            if (in_array($lockedAttempt->status, ['paid', 'refund_pending', 'refunded'], true)) {
                if ($lockedAttempt->status === 'refunded') {
                    return 'refunded';
                }
                $paymentStatus = $lockedOrder->syncPaymentStatusFromAttempts();
                if ($paymentStatus === 'refund_pending') {
                    $this->cancellationService->cancelExpiredPayment($lockedOrder);
                }
                return in_array($paymentStatus, ['refund_pending', 'paid_refund_pending'], true)
                    ? $paymentStatus : 'success';
            }

            $hasAcceptedPayment = in_array($lockedOrder->payment_status, ['paid', 'paid_refund_pending'], true)
                || $lockedOrder->momoPaymentAttempts()
                ->where('status', 'paid')
                ->exists();
            $isOpenForPayment = $lockedOrder->status !== 'cancelled'
                && (! $lockedOrder->payment_expires_at || $lockedOrder->payment_expires_at->isFuture())
                && (! $lockedAttempt->expires_at || $lockedAttempt->expires_at->isFuture());
            $attemptStatus = $isOpenForPayment && ! $hasAcceptedPayment ? 'paid' : 'refund_pending';

            $lockedAttempt->update([
                'status' => $attemptStatus,
                'transaction_id' => (string) $payload['transId'],
                'response_time' => (int) $payload['responseTime'],
            ]);
            $lockedOrder->update([
                'payment_method' => $hasAcceptedPayment ? $lockedOrder->payment_method : Order::PAYMENT_METHOD_MOMO,
                'momo_transaction_id' => (string) $payload['transId'],
                'momo_response_time' => (int) $payload['responseTime'],
            ]);

            $paymentStatus = $lockedOrder->syncPaymentStatusFromAttempts();
            if ($paymentStatus === 'refund_pending') {
                $this->cancellationService->cancelExpiredPayment($lockedOrder);
            }

            return match ($paymentStatus) {
                'refund_pending' => 'refund_pending',
                'paid_refund_pending' => 'paid_refund_pending',
                default => 'success',
            };
        });
    }
}
