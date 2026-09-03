<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $keyword = trim($request->string('keyword')->toString());
        $allowed = ['pending', 'shipping', 'completed', 'cancelled'];
        $rawStatusCounts = $request->user()->orders()
            ->visibleInOrderHistory()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $statusCounts = collect([
            'pending' => (int) $rawStatusCounts->get('pending', 0) + (int) $rawStatusCounts->get('processing', 0),
            'shipping' => (int) $rawStatusCounts->get('shipping', 0),
            'completed' => (int) $rawStatusCounts->get('completed', 0),
            'cancelled' => (int) $rawStatusCounts->get('cancelled', 0),
        ]);

        $orders = $request->user()->orders()
            ->visibleInOrderHistory()
            ->with(['items.variant.product'])
            ->when($status === 'pending', fn ($query) => $query->whereIn('status', ['pending', 'processing']))
            ->when(in_array($status, $allowed, true) && $status !== 'pending', fn ($query) => $query->where('status', $status))
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($searchQuery) use ($keyword): void {
                    if (ctype_digit($keyword)) {
                        $searchQuery->whereKey((int) $keyword);
                    }

                    $method = ctype_digit($keyword) ? 'orWhereHas' : 'whereHas';
                    $searchQuery->{$method}('items', fn ($itemQuery) => $itemQuery->where('product_name', 'like', '%'.$keyword.'%'));
                });
            })
            ->activeFirst()
            ->get();

        return view('orders.index', compact('orders', 'status', 'statusCounts', 'keyword'));
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load(['items.variant.product']);
        $subtotal = (float) $order->items->sum(fn ($item) => (float) $item->price * $item->quantity);
        $shippingFee = (float) ($order->shipping_fee ?? max(0, (float) $order->total - $subtotal));
        return view('orders.show', compact('order', 'subtotal', 'shippingFee'));
    }

    public function reorder(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load('items.variant');
        $savedCart = $request->user()->cartItems()->pluck('quantity', 'product_variant_id')->all();
        $sessionCart = $request->session()->get('cart', []);
        $added = 0;

        foreach ($order->items->groupBy('product_variant_id') as $variantId => $items) {
            $variant = $items->first()->variant;
            if (! $variant || $variant->stock < 1) {
                continue;
            }

            $requestedQuantity = (int) $items->sum('quantity');
            $currentQuantity = (int) ($savedCart[$variantId] ?? $sessionCart[(string) $variantId] ?? 0);
            $newQuantity = min((int) $variant->stock, $currentQuantity + $requestedQuantity);

            if ($newQuantity <= $currentQuantity) {
                continue;
            }

            $request->user()->cartItems()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                ['quantity' => $newQuantity]
            );
            unset($sessionCart[(string) $variant->id]);
            $sessionCart = [(string) $variant->id => $newQuantity] + $sessionCart;
            $added += $newQuantity - $currentQuantity;
        }

        if ($added === 0) {
            return back()->withErrors(['reorder' => 'Các sản phẩm trong đơn hiện đã hết hàng hoặc đã đủ số lượng tối đa trong giỏ.']);
        }

        $request->session()->put('cart', $sessionCart);

        return redirect()->route('cart.index')->with('status', "Đã thêm {$added} sản phẩm từ đơn #{$order->id} vào giỏ hàng.");
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $reason = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Bạn cần nhập lý do hủy đơn.',
            'cancellation_reason.min' => 'Lý do hủy đơn cần có ít nhất 5 ký tự.',
            'cancellation_reason.max' => 'Lý do hủy đơn không được dài quá 500 ký tự.',
        ])['cancellation_reason'];

        DB::transaction(function () use ($request, $order, $reason): void {
            $lockedOrder = $request->user()->orders()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, ['pending', 'processing', 'shipping'], true)) {
                abort(422, 'Không thể hủy đơn hàng đã hoàn thành hoặc đã hủy.');
            }

            $lockedOrder->items()
                ->whereNotNull('product_variant_id')
                ->get(['product_variant_id', 'quantity'])
                ->each(function ($item): void {
                    ProductVariant::query()
                        ->whereKey($item->product_variant_id)
                        ->increment('stock', $item->quantity);
                });

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancellation_reason' => trim($reason),
            ]);
        });

        return back()->with('status', 'Đã hủy đơn hàng và hoàn lại số lượng sản phẩm vào kho.');
    }
}
