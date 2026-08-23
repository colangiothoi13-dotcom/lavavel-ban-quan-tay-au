<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class StorefrontController
{
    public function home(Request $request)
    {
        $layout = $request->user()?->isAdmin() ? 'layouts.app' : 'layouts.shop';
        $categories = Schema::hasTable('categories') ? Category::orderBy('name')->get() : collect();

        if (! Schema::hasTable('products')) {
            return view('shop.home', [
                'products' => new LengthAwarePaginator([], 0, 12),
                'layout' => $layout,
            'categories' => $categories,
            ]);
        }

        $query = Product::with('variants');

        if (! $request->user()?->isAdmin() && $request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->string('keyword') . '%');
        }

        if (! $request->user()?->isAdmin() && $request->filled('size')) {
            $query->whereHas('variants', function ($variantQuery) use ($request) {
                $variantQuery->where('size', $request->string('size'));
            });
        }

        if (! $request->user()?->isAdmin() && $request->filled('gender')) {
            $query->where('gender', $request->string('gender'));
        }

        if (! $request->user()?->isAdmin() && $request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if (! $request->user()?->isAdmin() && $request->filled('min_price')) {
            $query->where('base_price', '>=', $request->input('min_price'));
        }

        if (! $request->user()?->isAdmin() && $request->filled('max_price')) {
            $query->where('base_price', '<=', $request->input('max_price'));
        }

        match (! $request->user()?->isAdmin() ? $request->input('sort_by') : null) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('shop.home', compact('products', 'layout', 'categories'));
    }

    public function productSuggestions(Request $request)
    {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword === '') {
            return response()->json([]);
        }

        $products = Product::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($keyword, 'UTF-8') . '%'])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'image']);

        return response()->json($products->map(fn (Product $product) => [
            'name' => $product->name,
            'url' => route('shop.products.show', $product),
            'image' => $product->image ? asset('storage/' . $product->image) : null,
        ])->values());
    }

    public function show(Product $product)
    {
        $product->load('variants');

        return view('shop.show', ['product' => $product, 'selectedVariant' => null]);
    }

    public function showVariant(Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        $product->load('variants');

        return view('shop.show', ['product' => $product, 'selectedVariant' => $variant]);
    }

    public function cart(Request $request)
    {
        $items = $this->cartItems($request);

        return view('shop.cart', compact('items'));
    }

    public function addToCart(Request $request, Product $product)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'numeric', 'integer', 'min:1', 'max:9223372036854775807'],
        ]);
        $variant = $product->variants()->findOrFail($data['variant_id']);
        $quantity = (int) $data['quantity'];

        if ($variant->stock < $quantity) {
            return back()->withErrors(['quantity' => 'Số lượng sản phẩm trong kho không đủ.']);
        }

        $cart = $request->session()->get('cart', []);
        $key = (string) $variant->id;
        $cart[$key] = min(($cart[$key] ?? 0) + $quantity, $variant->stock);
        $request->session()->put('cart', $cart);
        $this->saveCartItem($request, $variant->id, $cart[$key]);

        return redirect()->route('cart.index')->with('status', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function updateCart(Request $request, ProductVariant $variant)
    {
        $quantity = $request->validate(['quantity' => ['required', 'numeric', 'integer', 'min:1', 'max:9223372036854775807']])['quantity'];
        abort_if($variant->stock < $quantity, 422, 'Số lượng sản phẩm trong kho không đủ.');
        $cart = $request->session()->get('cart', []);
        $cart[(string) $variant->id] = $quantity;
        $request->session()->put('cart', $cart);
        $this->saveCartItem($request, $variant->id, $quantity);

        return back()->with('status', 'Đã cập nhật giỏ hàng.');
    }

    public function replaceVariant(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'new_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
        ]);
        $newVariant = ProductVariant::findOrFail($data['new_variant_id']);
        abort_unless($newVariant->product_id === $variant->product_id, 422, 'Biến thể không hợp lệ.');

        $cart = $request->session()->get('cart', []);
        $oldKey = (string) $variant->id;
        $newKey = (string) $newVariant->id;
        $quantity = (int) ($cart[$oldKey] ?? 0);
        abort_if($quantity < 1, 404);
        abort_if($newVariant->stock < $quantity, 422, 'Số lượng biến thể mới trong kho không đủ.');

        unset($cart[$oldKey]);
        $cart[$newKey] = $quantity;
        $request->session()->put('cart', $cart);
        if ($request->user()) {
            $request->user()->cartItems()->where('product_variant_id', $variant->id)->delete();
            $this->saveCartItem($request, $newVariant->id, $quantity);
        }

        return back()->with('status', 'Đã đổi biến thể trong giỏ hàng.');
    }

    public function removeFromCart(Request $request, ProductVariant $variant)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[(string) $variant->id]);
        $request->session()->put('cart', $cart);
        if ($request->user()) {
            $request->user()->cartItems()->where('product_variant_id', $variant->id)->delete();
        }

        return back()->with('status', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }

    public function checkout(Request $request)
    {
        $selectedIds = $request->input('selected_items', []);
        $items = $this->cartItems($request, $selectedIds);
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Vui lòng chọn ít nhất một sản phẩm.']);
        }

        return view('shop.checkout', compact('items'));
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
        ]);
        $selectedIds = $request->input('selected_items', []);
        $items = $this->cartItems($request, $selectedIds);
        abort_if($items->isEmpty(), 422, 'Vui lòng chọn ít nhất một sản phẩm.');

        DB::transaction(function () use ($items, $request) {
            foreach ($items as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item['variant']->id);
                abort_if($variant->stock < $item['quantity'], 422, 'Một sản phẩm vừa hết hàng.');
                $variant->decrement('stock', $item['quantity']);
            }

            $cart = $request->session()->get('cart', []);
            foreach ($items as $item) {
                unset($cart[(string) $item['variant']->id]);
            }
            $request->session()->put('cart', $cart);
            if ($request->user()) {
                $request->user()->cartItems()->whereIn('product_variant_id', $items->pluck('variant.id'))->delete();
            }
        });

        return redirect()->route('shop.home')->with('status', 'Đặt hàng thành công. Chúng tôi sẽ liên hệ với bạn sớm.');
    }

    private function cartItems(Request $request, ?array $selectedIds = null)
    {
        $cart = $request->session()->get('cart', []);
        if ($request->user()) {
            $cart = $request->user()->cartItems()->pluck('quantity', 'product_variant_id')->all();
        }
        if ($selectedIds !== null) {
            $selectedIds = array_map('strval', $selectedIds);
            $cart = array_intersect_key($cart, array_flip($selectedIds));
        }
        $variants = ProductVariant::with('product')->whereIn('id', array_keys($cart))->get();

        return $variants->map(function (ProductVariant $variant) use ($cart) {
            $quantity = (int) ($cart[(string) $variant->id] ?? 0);
            $price = $variant->price ?: $variant->product->base_price;

            return [
                'variant' => $variant,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $price * $quantity,
            ];
        })->filter(fn (array $item) => $item['quantity'] > 0)->values();
    }

    private function saveCartItem(Request $request, int $variantId, int $quantity): void
    {
        if ($request->user()) {
            $request->user()->cartItems()->updateOrCreate(
                ['product_variant_id' => $variantId],
                ['quantity' => $quantity]
            );
        }
    }
}