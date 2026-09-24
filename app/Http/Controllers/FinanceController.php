<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\CodPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    private const PAYMENT_METHODS = [
        'cod' => 'COD',
        'momo' => 'MoMo',
        'bank_transfer' => 'Chuyển khoản nội địa',
        'international_transfer' => 'Chuyển khoản quốc tế',
    ];

    private const PAYMENT_STATUSES = [
        'unpaid',
        'paid',
        'failed',
        'cancelled',
        'refund_pending',
        'refunded',
    ];

    public function __construct(private readonly CodPaymentService $codPaymentService) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $filters = $this->filters($request);
        $orders = $this->filteredOrdersQuery($from, $to, $filters);
        $filteredOrders = (clone $orders)->with('paymentTransactions')->get();

        $paidOrders = $filteredOrders->filter(
            fn (Order $order): bool => in_array($order->payment_status, ['paid', 'paid_refund_pending'], true)
        );
        $failedOrders = $filteredOrders->filter(
            fn (Order $order): bool => $order->paymentTransactions->contains('status', PaymentTransaction::STATUS_FAILED)
        );
        $pendingPaymentOrders = $filteredOrders->where('payment_status', 'unpaid');
        $pendingMomoOrders = $pendingPaymentOrders->filter(fn (Order $order): bool => $order->isMomoOrder());
        $cancelledOrders = $filteredOrders->where('status', 'cancelled');
        $refundPendingOrders = $filteredOrders->whereIn('payment_status', ['refund_pending', 'paid_refund_pending']);
        $refundedOrders = $filteredOrders->where('payment_status', 'refunded');

        $summaryCards = [
            [
                'label' => 'Tổng giá trị đơn hàng',
                'amount' => (float) $filteredOrders->sum('total'),
                'count' => $filteredOrders->count(),
                'hint' => 'đơn, bao gồm đơn đã hủy',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Chờ thanh toán',
                'amount' => (float) $pendingPaymentOrders->sum('total'),
                'count' => $pendingPaymentOrders->count(),
                'hint' => 'đơn',
                'tone' => 'orange',
            ],
            [
                'label' => 'Đang chờ MoMo',
                'amount' => (float) $pendingMomoOrders->sum('total'),
                'count' => $pendingMomoOrders->count(),
                'hint' => 'đơn',
                'tone' => 'orange',
            ],
            [
                'label' => 'Đã thanh toán',
                'amount' => (float) $paidOrders->sum('total'),
                'count' => $paidOrders->count(),
                'hint' => 'đơn',
                'tone' => 'green',
            ],
            [
                'label' => 'Thanh toán thất bại',
                'amount' => (float) $failedOrders->sum('total'),
                'count' => $failedOrders->count(),
                'hint' => 'đơn',
                'tone' => 'red',
            ],
            [
                'label' => 'Đã hủy',
                'amount' => (float) $cancelledOrders->sum('total'),
                'count' => $cancelledOrders->count(),
                'hint' => 'đơn',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Chờ hoàn tiền',
                'amount' => (float) $refundPendingOrders->sum('total'),
                'count' => $refundPendingOrders->count(),
                'hint' => 'đơn',
                'tone' => 'cyan',
            ],
            [
                'label' => 'Đã hoàn tiền',
                'amount' => (float) $refundedOrders->sum('total'),
                'count' => $refundedOrders->count(),
                'hint' => 'đơn',
                'tone' => 'neutral',
            ],
        ];

        $paymentMethodStats = collect(self::PAYMENT_METHODS)->map(
            function (string $label, string $method) use ($filteredOrders): array {
                $methodOrders = $filteredOrders->filter(
                    fn (Order $order): bool => $this->paymentMethodKey($order->payment_method) === $method
                );
                $paid = $methodOrders->filter(
                    fn (Order $order): bool => in_array($order->payment_status, ['paid', 'paid_refund_pending'], true)
                );

                return [
                    'method' => $method,
                    'label' => $label,
                    'count' => $methodOrders->count(),
                    'total' => (float) $methodOrders->sum('total'),
                    'paid' => (float) $paid->sum('total'),
                ];
            }
        )->values();

        return view('admin.finance.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'keyword' => $filters['keyword'],
            'minAmount' => $filters['min_amount'],
            'maxAmount' => $filters['max_amount'],
            'paymentMethod' => $filters['payment_method'],
            'paymentStatus' => $filters['payment_status'],
            'ordersCount' => $filteredOrders->count(),
            'totalFilteredAmount' => (float) $filteredOrders->sum('total'),
            'summaryCards' => $summaryCards,
            'paymentMethodStats' => $paymentMethodStats,
            'paymentMethodOptions' => self::PAYMENT_METHODS,
            'paymentStatusOptions' => self::PAYMENT_STATUSES,
            'paymentStatusLabels' => $this->paymentStatusLabels(),
            // Kept for existing consumers/tests of the original finance view.
            'totalOrders' => $filteredOrders->count(),
            'totalOrderValue' => (float) $filteredOrders->sum('total'),
            'collectedAmount' => (float) $paidOrders->sum('total'),
            'paidOrderCount' => $paidOrders->count(),
        ]);
    }

    public function transactions(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $filters = $this->filters($request);
        $sort = $request->string('sort')->toString();
        if (! in_array($sort, ['latest', 'oldest', 'amount_desc', 'amount_asc'], true)) {
            $sort = 'latest';
        }

        $orders = $this->filteredOrdersQuery($from, $to, $filters)
            ->with('paymentTransactions');

        match ($sort) {
            'oldest' => $orders->orderBy('created_at')->orderBy('id'),
            'amount_desc' => $orders->orderByDesc('total')->orderByDesc('id'),
            'amount_asc' => $orders->orderBy('total')->orderBy('id'),
            default => $orders->latest('created_at')->latest('id'),
        };

        $transactions = $orders->paginate(25)->withQueryString();

        return view('admin.finance.transactions', [
            'transactions' => $transactions,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'keyword' => $filters['keyword'],
            'minAmount' => $filters['min_amount'],
            'maxAmount' => $filters['max_amount'],
            'paymentMethod' => $filters['payment_method'],
            'paymentStatus' => $filters['payment_status'],
            'sort' => $sort,
            'paymentMethodOptions' => self::PAYMENT_METHODS,
            'paymentStatusOptions' => self::PAYMENT_STATUSES,
            'paymentStatusLabels' => $this->paymentStatusLabels(),
        ]);
    }

    public function updateCodPayment(Request $request, Order $order): RedirectResponse
    {
        $paymentStatus = $request->validate([
            'payment_status' => ['required', 'in:paid,unpaid'],
        ])['payment_status'];

        abort_unless($order->isCashPayment(), 422, 'Chỉ có thể cập nhật thanh toán COD cho đơn tiền mặt.');
        $this->codPaymentService->update($order, $paymentStatus);

        return back()->with('status', $paymentStatus === 'paid'
            ? 'Đã xác nhận thu tiền COD và ghi nhận giao dịch.'
            : 'Đã chuyển giao dịch COD về trạng thái chưa thanh toán.');
    }

    /** @return array{Carbon, Carbon} */
    private function dateRange(Request $request): array
    {
        $fromValue = $request->input('from') ?: $request->input('from_date');
        $toValue = $request->input('to') ?: $request->input('to_date');
        $validated = validator([
            'from' => $fromValue,
            'to' => $toValue,
        ], [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        return [
            Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay(),
            Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay(),
        ];
    }

    /** @return array{keyword: string, min_amount: string, max_amount: string, payment_method: string, payment_status: string} */
    private function filters(Request $request): array
    {
        $min = trim($request->string('min_amount')->toString() ?: $request->string('amount_from')->toString());
        $max = trim($request->string('max_amount')->toString() ?: $request->string('amount_to')->toString());
        $method = $request->string('payment_method')->toString();
        $status = $request->string('payment_status')->toString();

        return [
            'keyword' => trim($request->string('keyword')->toString() ?: $request->string('search')->toString()),
            'min_amount' => preg_match('/^\d+(?:\.\d+)?$/', $min) === 1 ? $min : '',
            'max_amount' => preg_match('/^\d+(?:\.\d+)?$/', $max) === 1 ? $max : '',
            'payment_method' => array_key_exists($method, self::PAYMENT_METHODS) ? $method : '',
            'payment_status' => in_array($status, self::PAYMENT_STATUSES, true) ? $status : '',
        ];
    }

    private function filteredOrdersQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['keyword'] !== '', function (Builder $query) use ($filters): void {
                $keyword = $filters['keyword'];
                $like = '%'.$keyword.'%';
                $query->where(function (Builder $search) use ($keyword, $like): void {
                    $search->where('recipient_name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('ghn_order_code', 'like', $like);

                    if (ctype_digit($keyword)) {
                        $search->orWhereKey((int) $keyword);
                    }
                });
            })
            ->when($filters['min_amount'] !== '', fn (Builder $query) => $query->where('total', '>=', $filters['min_amount']))
            ->when($filters['max_amount'] !== '', fn (Builder $query) => $query->where('total', '<=', $filters['max_amount']))
            ->when($filters['payment_method'] !== '', function (Builder $query) use ($filters): void {
                $methods = match ($filters['payment_method']) {
                    'cod' => [Order::PAYMENT_METHOD_CASH, Order::PAYMENT_METHOD_COD],
                    'momo' => [Order::PAYMENT_METHOD_MOMO, Order::PAYMENT_METHOD_MOMO_ATM, Order::PAYMENT_METHOD_MOMO_CC],
                    default => [$filters['payment_method']],
                };
                $query->whereIn('payment_method', $methods);
            })
            ->when($filters['payment_status'] !== '', function (Builder $query) use ($filters): void {
                match ($filters['payment_status']) {
                    'unpaid' => $query->where('payment_status', 'unpaid'),
                    'paid' => $query->whereIn('payment_status', ['paid', 'paid_refund_pending']),
                    'refund_pending' => $query->whereIn('payment_status', ['refund_pending', 'paid_refund_pending']),
                    'refunded' => $query->where('payment_status', 'refunded'),
                    'cancelled' => $query->where(function (Builder $cancelled) {
                        $cancelled->where('status', 'cancelled')
                            ->orWhereHas('paymentTransactions', fn (Builder $transactions) => $transactions->where('status', PaymentTransaction::STATUS_CANCELLED));
                    }),
                    'failed' => $query->whereHas('paymentTransactions', fn (Builder $transactions) => $transactions->where('status', PaymentTransaction::STATUS_FAILED)),
                    default => null,
                };
            });
    }

    private function paymentMethodKey(?string $method): string
    {
        return match ($method) {
            Order::PAYMENT_METHOD_CASH, Order::PAYMENT_METHOD_COD => 'cod',
            Order::PAYMENT_METHOD_MOMO, Order::PAYMENT_METHOD_MOMO_ATM, Order::PAYMENT_METHOD_MOMO_CC => 'momo',
            default => $method ?: 'other',
        };
    }

    /** @return array<string, string> */
    private function paymentStatusLabels(): array
    {
        return [
            'unpaid' => 'Chưa thanh toán',
            'paid' => 'Đã thanh toán',
            'failed' => 'Thanh toán thất bại',
            'cancelled' => 'Đã hủy',
            'refund_pending' => 'Chờ hoàn tiền',
            'refunded' => 'Đã hoàn tiền',
        ];
    }
}
