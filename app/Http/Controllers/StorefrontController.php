<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Services\GHNService;
use App\Models\Order;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Payments\MomoPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StorefrontController
{
    public function __construct(
        private readonly GHNService $ghn,
        private readonly MomoPaymentService $momoPaymentService
    ) {}

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
            $query->where('name', 'like', '%'.$request->string('keyword').'%');
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
            default => $query->latest('created_at')->orderByDesc('id'),
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
            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($keyword, 'UTF-8').'%'])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'image']);

        $isAdmin = $request->user()?->isAdmin() ?? false;

        return response()->json($products->map(fn (Product $product) => [
            'name' => $product->name,
            'url' => $isAdmin ? route('products.edit', $product) : route('shop.products.show', $product),
            'image' => $product->image ? asset('storage/'.$product->image) : null,
        ])->values());
    }

    public function show(Product $product)
    {
        $product->load('variants');

        return view('shop.show', [
            'product' => $product,
            'selectedVariant' => null,
            'layout' => request()->user() ? 'layouts.app' : 'layouts.shop',
        ]);
    }

    public function showVariant(Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        $product->load('variants');

        return view('shop.show', [
            'product' => $product,
            'selectedVariant' => $variant,
            'layout' => request()->user() ? 'layouts.app' : 'layouts.shop',
        ]);
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
            'return_to' => ['nullable', 'string', 'max:2048'],
            'purchase_action' => ['nullable', 'in:add_to_cart,buy_now'],
        ]);
        $variant = $product->variants()->findOrFail($data['variant_id']);
        $quantity = (int) $data['quantity'];

        if ($variant->stock < $quantity) {
            return back()->withErrors(['quantity' => 'Số lượng sản phẩm trong kho không đủ.']);
        }

        $cart = $request->session()->get('cart', []);
        $key = (string) $variant->id;
        $buyNow = ($data['purchase_action'] ?? 'add_to_cart') === 'buy_now';
        $newQuantity = $buyNow ? $quantity : (int) ($cart[$key] ?? 0) + $quantity;

        if ($newQuantity > $variant->stock) {
            return back()->withErrors([
                'quantity' => 'Trong giỏ đã có sản phẩm này. Tổng số lượng không được vượt quá '.$variant->stock.' sản phẩm còn trong kho.',
            ])->withInput();
        }

        unset($cart[$key]);
        $cart = [$key => $newQuantity] + $cart;
        $request->session()->put('cart', $cart);
        $this->saveCartItem($request, $variant->id, $cart[$key]);

        if ($buyNow) {
            return redirect()->route('checkout', ['selected_items' => [$variant->id]]);
        }

        $returnTo = $this->safeStorefrontReturnUrl($request, $data['return_to'] ?? null);

        return redirect()->to($returnTo)->with('status', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function updateCart(Request $request, ProductVariant $variant)
    {
        $quantity = $request->validate(['quantity' => ['required', 'numeric', 'integer', 'min:1', 'max:9223372036854775807']])['quantity'];
        abort_if($variant->stock < $quantity, 422, 'Số lượng sản phẩm trong kho không đủ.');
        $cart = $request->session()->get('cart', []);
        $key = (string) $variant->id;
        unset($cart[$key]);
        $cart = [$key => $quantity] + $cart;
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
        $existingNewQuantity = (int) ($cart[$newKey] ?? 0);

        // Giỏ của người dùng đăng nhập được lưu lâu dài trong database. Session có
        // thể trống sau khi khởi động lại trình duyệt/server, dù trang giỏ hàng vẫn
        // hiển thị các dòng lấy từ cart_items.
        if ($request->user()) {
            $savedQuantities = $request->user()->cartItems()
                ->whereIn('product_variant_id', [$variant->id, $newVariant->id])
                ->pluck('quantity', 'product_variant_id');

            $quantity = (int) ($savedQuantities->get($variant->id) ?? $quantity);
            $existingNewQuantity = (int) ($savedQuantities->get($newVariant->id) ?? $existingNewQuantity);
        }

        abort_if($quantity < 1, 404);
        $newQuantity = $quantity + $existingNewQuantity;
        abort_if($newVariant->stock < $newQuantity, 422, 'Số lượng biến thể mới trong kho không đủ.');

        unset($cart[$oldKey]);
        unset($cart[$newKey]);
        $cart = [$newKey => $newQuantity] + $cart;
        $request->session()->put('cart', $cart);
        if ($request->user()) {
            $request->user()->cartItems()->where('product_variant_id', $variant->id)->delete();
            $this->saveCartItem($request, $newVariant->id, $newQuantity);
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

        $addresses = $request->user()?->addresses()->latest('is_default')->latest()->get() ?? collect();

        return view('shop.checkout-ghn', compact('items', 'addresses'));
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255', 'required_without:saved_address_id'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:saved_address_id'],
            'address' => ['nullable', 'string', 'max:500', 'required_without:saved_address_id'],
            'saved_address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'payment_method' => ['required', 'in:cash,bank_transfer,momo,cod'],
            'ghn_district_id' => ['nullable', 'integer', 'min:1'],
            'ghn_ward_code' => ['nullable', 'string', 'max:20'],
        ]);
        $data['payment_method'] = $this->normalizePaymentMethod($data['payment_method']);
        if (! empty($data['saved_address_id'])) {
            $savedAddress = $request->user()?->addresses()->findOrFail($data['saved_address_id']);
            $data['name'] = $savedAddress->recipient_name;
            $data['phone'] = $savedAddress->phone;
            $data['address'] = $savedAddress->full_address;
            $data['ghn_district_id'] = $savedAddress->ghn_district_id;
            $data['ghn_ward_code'] = $savedAddress->ghn_ward_code;
        }
        $selectedIds = $request->input('selected_items', []);
        $items = $this->cartItems($request, $selectedIds);
        abort_if($items->isEmpty(), 422, 'Vui lòng chọn ít nhất một sản phẩm.');

        if (empty($data['ghn_district_id']) || empty($data['ghn_ward_code'])) {
            throw ValidationException::withMessages([
                'address' => 'Địa chỉ chưa có mã khu vực GHN. Vui lòng chọn "Thêm địa chỉ khác" và chọn đủ Tỉnh/Quận/Phường.',
            ]);
        }

        $subtotal = (int) $items->sum('total');
        try {
            $fee = $this->ghn->calculateFee([
                'from_district_id' => (int) config('services.ghn.from_district_id'),
                'to_district_id' => (int) $data['ghn_district_id'],
                'to_ward_code' => $data['ghn_ward_code'],
                'service_type_id' => (int) config('services.ghn.service_type_id', 2),
                'insurance_value' => min($subtotal, 5000000),
                'weight' => (int) config('services.ghn.default_weight', 500),
                'length' => (int) config('services.ghn.default_length', 20),
                'width' => (int) config('services.ghn.default_width', 15),
                'height' => (int) config('services.ghn.default_height', 10),
            ]);
            $shippingFee = (int) ($fee['total'] ?? 0);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['shipping' => $exception->getMessage()]);
        }

        $order = null;
        DB::transaction(function () use ($items, $request, $data, $subtotal, $shippingFee, &$order): void {
            $order = $request->user()->orders()->create([
                'recipient_name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'unpaid',
                'payment_expires_at' => $data['payment_method'] === Order::PAYMENT_METHOD_MOMO
                    ? now()->addMinutes((int) config('services.momo.payment_timeout', 30))
                    : null,
                'total' => $subtotal + $shippingFee,
                'shipping_fee' => $shippingFee,
                'ghn_district_id' => $data['ghn_district_id'],
                'ghn_ward_code' => $data['ghn_ward_code'],
            ]);
            foreach ($items as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item['variant']->id);
                abort_if($variant->stock < $item['quantity'], 422, 'Một sản phẩm vừa hết hàng.');
                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => trim($variant->color.' - '.$variant->size, ' -'),
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
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
        if (! $order instanceof Order) {
            throw new RuntimeException('Không thể tạo đơn hàng.');
        }

        if ($order->payment_method === Order::PAYMENT_METHOD_MOMO) {
            try {
                $response = $this->momoPaymentService->createPayment(
                    $order,
                    route('momo.result'),
                    route('momo.ipn')
                );

                return redirect()->away($response['pay_url']);
            } catch (Throwable $exception) {
                return back()->withErrors(['payment_method' => $exception->getMessage()]);
            }
        }

        return redirect()->route('shop.home')->with('status', 'Đặt hàng thành công. Chúng tôi sẽ liên hệ với bạn sớm.');
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'cod' => Order::PAYMENT_METHOD_CASH,
            default => $paymentMethod,
        };
    }

    private function cartItems(Request $request, ?array $selectedIds = null)
    {
        $cart = $request->session()->get('cart', []);
        if ($request->user()) {
            $savedCart = $request->user()->cartItems()
                ->latest('updated_at')
                ->pluck('quantity', 'product_variant_id')
                ->all();
            $orderedCart = [];
            foreach (array_keys($cart) as $variantId) {
                if (array_key_exists($variantId, $savedCart)) {
                    $orderedCart[$variantId] = $savedCart[$variantId];
                    unset($savedCart[$variantId]);
                }
            }
            $cart = $orderedCart + $savedCart;
        }
        if ($selectedIds !== null) {
            $selectedIds = array_map('strval', $selectedIds);
            $cart = array_intersect_key($cart, array_flip($selectedIds));
        }
        $variantOrder = array_flip(array_map('strval', array_keys($cart)));
        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($cart))
            ->get()
            ->sortBy(fn (ProductVariant $variant) => $variantOrder[(string) $variant->id] ?? PHP_INT_MAX)
            ->values();

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

    private function safeStorefrontReturnUrl(Request $request, ?string $returnTo): string
    {
        if ($returnTo) {
            $parts = parse_url($returnTo);
            $sameHost = ! isset($parts['host']) || strcasecmp($parts['host'], $request->getHost()) === 0;
            $validScheme = ! isset($parts['scheme']) || in_array($parts['scheme'], ['http', 'https'], true);

            if ($sameHost && $validScheme && str_starts_with($parts['path'] ?? '/', '/')) {
                return $returnTo;
            }
        }

        return route('shop.home').'#products';
    }
}
