<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

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

        return redirect()->route('cart.index')->with('status', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function updateCart(Request $request, ProductVariant $variant)
    {
        $quantity = $request->validate(['quantity' => ['required', 'numeric', 'integer', 'min:1', 'max:9223372036854775807']])['quantity'];
        abort_if($variant->stock < $quantity, 422, 'Số lượng sản phẩm trong kho không đủ.');
        $cart = $request->session()->get('cart', []);
        $cart[(string) $variant->id] = $quantity;
        $request->session()->put('cart', $cart);

        return back()->with('status', 'Đã cập nhật giỏ hàng.');
    }

    public function removeFromCart(Request $request, ProductVariant $variant)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[(string) $variant->id]);
        $request->session()->put('cart', $cart);

        return back()->with('status', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }

    public function checkout(Request $request)
    {
        $items = $this->cartItems($request);
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Giỏ hàng đang trống.']);
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
        $items = $this->cartItems($request);
        abort_if($items->isEmpty(), 422, 'Giỏ hàng đang trống.');

        foreach ($items as $item) {
            abort_if($item['variant']->stock < $item['quantity'], 422, 'Một sản phẩm vừa hết hàng.');
            $item['variant']->decrement('stock', $item['quantity']);
        }

        $request->session()->forget('cart');

        return redirect()->route('shop.home')->with('status', 'Đặt hàng thành công. Chúng tôi sẽ liên hệ với bạn sớm.');
    }

    private function cartItems(Request $request)
    {
        $cart = $request->session()->get('cart', []);
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
}