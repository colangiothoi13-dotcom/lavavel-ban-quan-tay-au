@extends($layout ?? 'layouts.shop', ['title' => $product->name])
@section('content')

@php
    $variant = $product->variants->first();
    $image = $product->image ? asset('storage/'.$product->image) : ($variant?->image ? asset('storage/'.$variant->image) : 'https://via.placeholder.com/600x600?text=No+Image');
    $selectedVariant = $selectedVariant ?? null;
    $selectedImage = $selectedVariant?->image ? asset('storage/'.$selectedVariant->image) : $image;
    $consultPrice = $selectedVariant?->price ?? $variant?->price ?? $product->base_price;

    $allImages = collect([$image]);
    foreach($product->variants as $v) {
        if($v->image) {
            $allImages->push(asset('storage/'.$v->image));
        }
    }
    $allImages = $allImages->unique()->values()->all();

    $variantsData = $product->variants->map(function($v) {
        return [
            'id' => $v->id,
            'color' => $v->color,
            'size' => $v->size,
            'stock' => $v->stock,
            'image' => $v->image ? asset('storage/'.$v->image) : null
        ];
    })->values()->all();
@endphp

<div class="product-frame">
<div class="detail">
    <div class="product-gallery">
        <img id="main-image" src="{{ $selectedImage }}" alt="{{ $product->name }}" onerror="this.onerror=null;this.src='https://via.placeholder.com/600x600?text=No+Image';">
    </div>
    <div class="product-information">
        <h1>{{ $product->name }}</h1>
        <p class="audience">{{ ['male' => 'Dành cho nam', 'female' => 'Dành cho nữ', 'unisex' => 'Dành cho nam và nữ'][$product->gender] ?? 'Dành cho nam và nữ' }}</p>
        <p>{{ $product->description ?: 'Sản phẩm quần Tây Âu chất lượng cao.' }}</p>

        <h2 class="variants-title">Chọn biến thể</h2>
        @if($product->variants->isEmpty())
            <p class="empty-variants">Sản phẩm chưa có biến thể.</p>
        @else
            @php
                $colors = $product->variants->pluck('color')->filter()->unique()->values();
                $sizes = $product->variants->pluck('size')->filter(fn ($size) => in_array($size, ['S', 'M', 'L', 'XL'], true))->unique()->values();
            @endphp
            
            <div class="choice-group">
                <strong>Màu sắc</strong>
                <div class="choice-list" id="color-choices">
                    @foreach($colors as $color)
                        @php
                            $matchingVariant = $product->variants->firstWhere('color', $color);
                            $colorImg = $matchingVariant?->image ? asset('storage/'.$matchingVariant->image) : '';
                        @endphp
                        <button type="button" class="choice" data-color="{{ $color }}" data-img="{{ $colorImg }}">{{ $color }}</button>
                    @endforeach
                </div>
            </div>
            
            <div class="choice-group">
                <strong>Size</strong>
                <div class="choice-list" id="size-choices">
                    @foreach($sizes as $size)
                        <button type="button" class="choice" data-size="{{ $size }}">{{ $size }}</button>
                    @endforeach
                </div>
                <button type="button" class="size-guide-link" id="open-size-guide">Xem hướng dẫn chọn size</button>
            </div>
            
            <p id="choice-message" class="choice-message">Hãy chọn màu sắc và size.</p>
        @endif

        <form action="{{ route('cart.add', $product) }}" method="POST" class="purchase-form">
            @csrf
            @if(request('return_to'))
                <input type="hidden" name="return_to" value="{{ request('return_to') }}">
            @endif
            <input type="hidden" name="variant_id" id="selected-variant-id" value="{{ $selectedVariant?->id }}">
            <div class="quantity-row">
                <label for="quantity">Số lượng</label>
                <input id="quantity" type="number" name="quantity" min="1" value="1">
            </div>
            
            <div class="action-buttons">
                <button
                    type="button"
                    class="consult-button purchase-button"
                    data-consult-trigger
                    data-consult-product-name="{{ $product->name }}"
                    data-consult-product-image="{{ $selectedImage }}"
                    data-consult-product-price="{{ number_format($consultPrice, 0, ',', '.') }} đ"
                    data-consult-product-url="{{ route('shop.products.show', $product) }}"
                    data-consult-login-url="{{ route('login') }}"
                >Hỏi tư vấn</button>
                <button type="submit" name="purchase_action" value="add_to_cart" id="add-to-cart" class="add-to-cart purchase-button" disabled>Chọn màu và size để mua</button>
                <button type="submit" name="purchase_action" value="buy_now" id="buy-now" class="buy-now purchase-button" disabled>Mua ngay</button>
                @auth
                    <form method="POST" action="{{ route('shop.wishlist.toggle', $product) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn-back" style="background:#fff1f2; color:#be123c; border-color:#fecdd3;">{{ auth()->user()->wishlistProducts()->whereKey($product->id)->exists() ? 'Bỏ yêu thích' : 'Yêu thích' }}</button>
                    </form>
                @endauth
				<a href="#" onclick="history.back(); return false;" class="btn-back">Quay lại cửa hàng</a>
			</div>
        </form>
    </div>
</div>
</div>

<div class="size-guide-modal" id="size-guide-modal" hidden>
    <div class="size-guide-dialog" role="dialog" aria-modal="true" aria-labelledby="size-guide-title">
        <button type="button" class="size-guide-close" id="close-size-guide" aria-label="Đóng">&times;</button>
        <h2 id="size-guide-title">Hướng dẫn chọn size</h2>
        <p class="size-guide-note">Bảng tham khảo theo chiều cao và cân nặng. Nếu số đo nằm giữa hai size, bạn có thể chọn size lớn hơn để mặc thoải mái.</p>

        @php
            $sizeGuide = [
                'male' => [
                    ['height' => '1m60-1m65', 'weight' => '55-60kg', 'size' => 'S'],
                    ['height' => '1m64-1m69', 'weight' => '60-65kg', 'size' => 'M'],
                    ['height' => '1m70-1m74', 'weight' => '66-70kg', 'size' => 'L'],
                    ['height' => '1m74-1m76', 'weight' => '70-76kg', 'size' => 'XL'],
                ],
                'female' => [
                    ['height' => '1m48-1m53', 'weight' => '38-43kg', 'size' => 'S'],
                    ['height' => '1m53-1m55', 'weight' => '43-46kg', 'size' => 'M'],
                    ['height' => '1m53-1m58', 'weight' => '46-53kg', 'size' => 'L'],
                    ['height' => '1m55-1m62', 'weight' => '53-57kg', 'size' => 'XL'],
                ],
            ];
            $guideGenders = $product->gender === 'unisex' ? ['male', 'female'] : [$product->gender];
        @endphp

        @foreach($guideGenders as $guideGender)
            <h3>{{ $guideGender === 'male' ? 'Nam' : 'Nữ' }}</h3>
            <table class="size-guide-table">
                <thead><tr><th>Chiều cao</th><th>Cân nặng</th><th>Size</th></tr></thead>
                <tbody>
                    @foreach($sizeGuide[$guideGender] as $guideRow)
                        <tr><td>{{ $guideRow['height'] }}</td><td>{{ $guideRow['weight'] }}</td><td><strong>{{ $guideRow['size'] }}</strong></td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
</div>

<style>
    .product-frame { width: 100%; max-width: none; margin: 0; padding: 0; background: transparent; border: 0; border-radius: 0; box-shadow: none; }
    .product-frame .detail { display: grid; grid-template-columns: minmax(300px, 43%) minmax(0, 1fr); gap: 40px; padding: 0; background: transparent; border: 0; box-shadow: none; border-radius: 0; }
    .product-gallery { display: flex; justify-content: center; align-items: flex-start; min-width: 0; }
    .product-information { min-width: 0; padding-top: 4px; }
    .product-information h1 { margin: 0 0 14px; color: #172033; font-size: clamp(27px, 2.2vw, 38px); line-height: 1.22; }
    .product-information > p:not(.audience):not(.choice-message):not(.empty-variants) { margin: 18px 0; color: #334155; font-size: 16px; line-height: 1.6; }
    .variants-title { font-size: 19px; margin: 25px 0 12px; color: #20252b; }
    .audience { display: inline-block; margin: 0 0 8px; padding: 4px 9px; background: #eef2ff; color: #3730a3; border-radius: 4px; font-size: 13px; font-weight: 700; }
    .choice-group { margin: 14px 0; }
    .choice-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .choice { border: 1px solid #ccd1d5; background: #fff; border-radius: 4px; padding: 8px 14px; cursor: pointer; transition: 0.2s; }
    .choice.active, .choice:hover { border-color: #b45309; background: #fff7ed; color: #9a3412; }
    .size-guide-link { margin-top: 10px; padding: 0; border: 0; background: transparent; color: #b45309; text-decoration: underline; cursor: pointer; font-size: 13px; }
    .choice-message { font-size: 13px; color: #6b7280; margin: 8px 0 14px; font-weight: 500; }
    .in-stock { color: #166534; } 
    .out-stock { color: #991b1b; } 
    .empty-variants { color: #6b7280; }
    .purchase-form { margin-top: 24px; padding-top: 18px; border-top: 1px solid #e1e5e8; }
    .quantity-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; font-weight: 700; }
    .quantity-row input { width: 80px; padding: 9px; border: 1px solid #ccd1d5; border-radius: 4px; }
    
    .action-buttons { display: flex; flex-wrap: wrap; gap: 12px; align-items: stretch; }
    .consult-button { flex: 1; padding: 12px; border: 1px solid #2563eb; border-radius: 4px; background: #eff6ff; color: #1d4ed8; font-weight: 700; cursor: pointer; transition: 0.2s; text-align: center; }
    .consult-button:hover { background: #dbeafe; }
    .add-to-cart { flex: 1; padding: 12px; border: 0; border-radius: 4px; background: #b45309; color: #fff; font-weight: 700; cursor: pointer; transition: 0.2s; text-align: center; }
    .buy-now { flex: 1; padding: 12px; border: 1px solid #ea580c; border-radius: 4px; background: #fff7ed; color: #c2410c; font-weight: 700; cursor: pointer; }
    .purchase-button:disabled { border-color: #9ca3af; background: #9ca3af; color: #fff; cursor: not-allowed; }
    
    .btn-back { padding: 12px 20px; border: 1px solid #cbd5e1; border-radius: 4px; background: #f1f5f9; color: #334155; font-weight: 700; text-decoration: none; transition: 0.2s; text-align: center; }
    .btn-back:hover { background: #e2e8f0; }

    .size-guide-modal[hidden] { display: none; }
    .size-guide-modal { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, 0.62); }
    .size-guide-dialog { position: relative; width: min(680px, 100%); max-height: 90vh; overflow: auto; padding: 28px; border-radius: 8px; background: #fff; box-shadow: 0 12px 35px rgba(15, 23, 42, 0.25); }
    .size-guide-dialog h2 { margin: 0 0 8px; color: #172033; }
    .size-guide-dialog h3 { margin: 22px 0 8px; color: #1e293b; }
    .size-guide-note { margin: 0; color: #64748b; font-size: 14px; line-height: 1.5; }
    .size-guide-close { position: absolute; top: 8px; right: 14px; border: 0; background: transparent; color: #64748b; font-size: 28px; cursor: pointer; }
    .size-guide-table { width: 100%; border-collapse: collapse; text-align: left; }
    .size-guide-table th, .size-guide-table td { padding: 10px 12px; border: 1px solid #dbe2e8; }
    .size-guide-table th { background: #d9edc9; color: #172033; }
    .size-guide-table td:last-child, .size-guide-table th:last-child { text-align: center; }

    #main-image { display: block; width: 100%; max-width: 460px; height: clamp(350px, 38vw, 480px); object-fit: contain; background: #f4f6f8; border-radius: 8px; border: 1px solid #e1e5e8; transition: opacity 0.3s ease; }

    @media (max-width: 900px) {
        .product-frame { padding: 0; }
        .product-frame .detail { grid-template-columns: 1fr; gap: 26px; }
        #main-image { max-width: 560px; height: min(65vw, 470px); }
    }

    @media (max-width: 560px) {
        .product-frame { padding: 0; }
        .product-information h1 { font-size: 25px; }
        #main-image { height: 320px; }
        .action-buttons { flex-direction: column; }
        .action-buttons > * { width: 100%; flex: none; }
    }
</style>

<script>
    const variants = @json($variantsData);
    const imagesList = @json($allImages);
    const defaultImage = @json($image);
    const mainImageEl = document.getElementById('main-image');

    let selectedColor = @json($selectedVariant?->color);
    let selectedSize = @json($selectedVariant?->size);
    let isUserLockedImage = Boolean(selectedColor);
    let autoSlideInterval = null;
    let imageIndex = 0;

    const startAutoSlide = () => {
        if (isUserLockedImage || imagesList.length <= 1) return;
        autoSlideInterval = setInterval(() => {
            imageIndex = (imageIndex + 1) % imagesList.length;
            mainImageEl.src = imagesList[imageIndex];
        }, 7000);
    };

    const stopAutoSlide = () => {
        if (autoSlideInterval) {
            clearInterval(autoSlideInterval);
            autoSlideInterval = null;
        }
    };

    const refreshChoices = () => {
        document.querySelectorAll('[data-color]').forEach(button => button.classList.toggle('active', button.dataset.color === selectedColor));
        document.querySelectorAll('[data-size]').forEach(button => button.classList.toggle('active', button.dataset.size === selectedSize));

        if (selectedColor) {
            const colorMatch = variants.find(v => v.color === selectedColor && v.image);
            if (colorMatch) mainImageEl.src = colorMatch.image;
        }

        const match = variants.find(v => v.color === selectedColor && v.size === selectedSize);
        const button = document.getElementById('add-to-cart');
        const buyNowButton = document.getElementById('buy-now');
        const variantId = document.getElementById('selected-variant-id');
        const messageEl = document.getElementById('choice-message');
        const quantityInput = document.getElementById('quantity');

        if (!match) {
            button.disabled = true;
            buyNowButton.disabled = true;
            button.textContent = 'Chọn màu và size để mua';
            variantId.value = '';
            if(!selectedColor) messageEl.textContent = 'Hãy chọn màu sắc và size.';
            return;
        }

        const stock = Number(match.stock);
        variantId.value = match.id;
        button.disabled = stock < 1;
        buyNowButton.disabled = stock < 1;
        quantityInput.max = stock;
        if (Number(quantityInput.value) > stock) quantityInput.value = Math.max(stock, 1);
        button.textContent = stock > 0 ? 'Thêm vào giỏ hàng' : 'Hết hàng';
        buyNowButton.textContent = stock > 0 ? 'Mua ngay' : 'Hết hàng';
        messageEl.innerHTML = stock > 0 
            ? `Đã chọn: <strong>${selectedColor} - Size ${selectedSize}</strong> (<span class="in-stock">Còn ${stock} sản phẩm</span>)` 
            : `<span class="out-stock">Tổ hợp này đã hết hàng.</span>`;
    };

    const chooseColor = (color) => {
        isUserLockedImage = true;
        stopAutoSlide();
        selectedColor = color;
        refreshChoices();
    };

    const chooseSize = (size) => {
        selectedSize = size;
        refreshChoices();
    };

    document.querySelectorAll('[data-color]').forEach(button => {
        button.addEventListener('click', () => chooseColor(button.dataset.color));
        button.addEventListener('mouseenter', () => {
            if (!isUserLockedImage && button.dataset.img) {
                mainImageEl.src = button.dataset.img;
            }
        });
        button.addEventListener('mouseleave', () => {
            if (!isUserLockedImage) {
                mainImageEl.src = defaultImage;
            }
        });
    });

    document.querySelectorAll('[data-size]').forEach(button => {
        button.addEventListener('click', () => chooseSize(button.dataset.size));
    });

    const sizeGuideModal = document.getElementById('size-guide-modal');
    document.getElementById('open-size-guide')?.addEventListener('click', () => {
        sizeGuideModal.hidden = false;
    });
    document.getElementById('close-size-guide')?.addEventListener('click', () => {
        sizeGuideModal.hidden = true;
    });
    sizeGuideModal?.addEventListener('click', event => {
        if (event.target === sizeGuideModal) sizeGuideModal.hidden = true;
    });
    
    refreshChoices();

    if (!isUserLockedImage) {
        startAutoSlide();
    }
</script>
@endsection
