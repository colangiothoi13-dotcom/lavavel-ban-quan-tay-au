<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\SimpleXlsx;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $orders = $this->reportableOrders($from, $to)->with('items')->oldest('created_at')->get();
        $dailyRevenue = $this->dailyRevenue($orders, $from, $to);
        $soldProducts = $this->soldProducts($orders);

        return view('admin.reports.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'orders' => $orders,
            'dailyRevenue' => $dailyRevenue,
            'maxDailyRevenue' => (float) $dailyRevenue->max('total'),
            'totalRevenue' => $orders->sum('total'),
            'totalOrders' => $orders->count(),
            'totalProducts' => $orders->sum(fn (Order $order) => $order->items->sum('quantity')),
            'soldProducts' => $soldProducts,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->dateRange($request);
        $orders = $this->reportableOrders($from, $to)
            ->with(['user:id,name,email', 'items'])
            ->oldest('created_at')
            ->get();
        $fileName = "bao-cao-doanh-thu-{$from->toDateString()}-den-{$to->toDateString()}.xlsx";
        $rows = [['Mã đơn', 'Ngày đặt', 'Khách hàng', 'Số điện thoại', 'Email', 'Sản phẩm', 'Số lượng', 'Doanh thu', 'Thanh toán']];

        foreach ($orders as $order) {
            $rows[] = [
                    $order->id,
                    $order->created_at->format('d/m/Y H:i'),
                    $order->user?->name ?? $order->recipient_name,
                    $order->phone,
                    $order->user?->email ?? '',
                    $order->items->pluck('product_name')->unique()->implode(', '),
                    $order->items->sum('quantity'),
                    (float) $order->total,
                    $order->payment_label,
            ];
        }

        return response()->download(
            SimpleXlsx::create($rows),
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /** @return array{Carbon, Carbon} */
    private function dateRange(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();

        return [$from, $to];
    }

    private function reportableOrders(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            ->whereIn('payment_status', ['paid', 'paid_refund_pending'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to]);
    }

    /** @return Collection<int, array{date: string, label: string, total: float}> */
    private function dailyRevenue(Collection $orders, Carbon $from, Carbon $to): Collection
    {
        $revenueByDate = $orders
            ->groupBy(fn (Order $order) => $order->created_at->toDateString())
            ->map(fn (Collection $dailyOrders) => (float) $dailyOrders->sum('total'));

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'total' => $revenueByDate->get($date->toDateString(), 0),
            ])->values();
    }

    /** @return Collection<int, array{product: string, variant: string, quantity: int, stock: int}> */
    private function soldProducts(Collection $orders): Collection
    {
        $quantities = $orders->flatMap->items
            ->groupBy('product_variant_id')
            ->map(fn (Collection $items) => (int) $items->sum('quantity'));
        $variants = ProductVariant::with('product')->whereIn('id', $quantities->keys())->get()->keyBy('id');

        return $quantities->map(function (int $quantity, string $variantId) use ($variants): array {
            $variant = $variants->get($variantId);

            return [
                'product' => $variant?->product?->name ?? 'Sản phẩm đã xóa',
                'variant' => $variant?->color || $variant?->size
                    ? trim($variant->color.' - '.$variant->size, ' -')
                    : 'Mặc định',
                'quantity' => $quantity,
                'stock' => (int) ($variant?->stock ?? 0),
            ];
        })->sortBy([['product', 'asc'], ['variant', 'asc']])->values();
    }
}
