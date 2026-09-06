@extends('layouts.app')
@section('content')
<style>
    /* Mở rộng khung chứa giỏ hàng ra toàn bộ không gian trống bên trái */
    .cart-container { 
        color: #334155; 
        max-width: 100% !important; 
        width: 100%;
        margin: 0; 
        padding: 0;
        position: relative; 
    }
    .cart-container h2 { font-size: 24px; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px; }
    .cart-product-group { margin-bottom: 22px; border-bottom: 2px solid #e2e8f0; }
    .cart-product-group:last-of-type { margin-bottom: 0; }
    .cart-product-heading { padding: 12px 15px; background: #f8fafc; border-left: 4px solid #b4860b; color: #1e293b; font-size: 16px; font-weight: 700; }
    .cart-product-heading span { margin-left: 8px; color: #64748b; font-size: 13px; font-weight: 400; }
    /* Cart Item Row */
    .cart-row {
        display: flex;
        align-items: center;
        padding: 20px 15px;
        background: transparent;
        border-bottom: 1px solid #f1f5f9;
        border-radius: 0;
        margin-bottom: 10px;
    }
    .cart-col-checkbox { width: 40px; text-align: center; }
    .cart-col-img { width: 90px; padding: 0 10px; flex-shrink: 0; }
    .cart-col-img img {
        width: 75px;
        height: 75px;
        object-fit: cover;
        object-position: center;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        display: block;
    }
    .cart-col-info { flex: 2; padding-right: 15px; position: relative; }
    .prod-name { font-size: 14px; color: #1e293b; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    /* Dropdown phân loại hàng */
    .variant-dropdown-toggle {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        color: #475569;
        cursor: pointer;
        margin-top: 6px;
    }
    .variant-dropdown-toggle:hover { background: #f1f5f9; }
    /* Modal popup chọn phân loại kiểu Shopee */
    .variant-modal {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: #fff;
        border: 1px solid #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 6px;
        padding: 15px;
        z-index: 100;
        width: 320px;
    }
    .variant-modal.active { display: block; }
    .variant-option-group { margin-bottom: 10px; }
    .variant-option-group label { font-size: 12px; color: #64748b; display: block; margin-bottom: 5px; font-weight: bold; }
    .variant-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .chip {
        padding: 6px 10px;
        font-size: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #fff;
        cursor: pointer;
        text-decoration: none;
        color: #334155;
    }
    .chip.active { border-color: #ee4d2d; color: #ee4d2d; background: #fff5f5; }
    .cart-col-price { width: 120px; text-align: center; color: #334155; font-size: 14px; }
    .cart-col-qty { width: 140px; display: flex; justify-content: center; }
    .qty-group { display: flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #fff; }
    .qty-btn { background: #fff; border: none; width: 30px; height: 30px; cursor: pointer; font-size: 16px; color: #475569; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .qty-btn:hover { background: #f1f5f9; }
    .qty-input { width: 40px; text-align: center; border: none; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; height: 30px; font-size: 14px; outline: none; }
    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .cart-col-total { width: 120px; text-align: center; color: #ee4d2d; font-weight: 600; font-size: 15px; }
    .cart-col-action { width: 80px; text-align: center; }
    .btn-delete { background: none; border: none; color: #ee4d2d; cursor: pointer; font-size: 14px; }
    .btn-delete:hover { text-decoration: underline; }
    /* Cart Footer */
    .cart-footer { display: flex; justify-content: space-between; align-items: center; background: transparent; padding: 20px 0 0; border-radius: 0; margin-top: 20px; border-top: 1px solid #e2e8f0; box-shadow: none; }
    .cart-total { font-size: 18px; font-weight: bold; color: #ee4d2d; }
    .btn { padding: 10px 24px; background: #ee4d2d; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-weight: 500; font-size: 14px; transition: 0.2s; }
    .btn:hover { background: #d73211; }
    .btn-light { background: #e2e8f0; color: #475569; }
    .btn-light:hover { background: #cbd5e1; }
    .empty-cart { padding: 40px 0; text-align: center; background: transparent; border-radius: 0; color: #64748b; }
    .variant-chip-form { display: inline-block; margin: 0; }
    .chip { font-family: inherit; }
    .cart-container.is-loading { opacity: .65; pointer-events: none; }
    
</style>

<div class="cart-container">
    <h2>Giỏ hàng của bạn</h2>
    @if($items->isEmpty())
        <div class="empty-cart">
            Giỏ hàng đang trống. <a href="{{ route('shop.home') }}" style="color: #2563eb; text-decoration: none; font-weight: bold;">Tiếp tục mua sắm &rarr;</a>
        </div>
    @else
        @php
            $groupedItems = $items->groupBy('variant.product_id');
        @endphp
        @foreach($groupedItems as $productItems)
        @php
            $groupProduct = $productItems->first()['variant']->product;
        @endphp
        <section class="cart-product-group" data-product-id="{{ $groupProduct->id }}">
        <div class="cart-product-heading">
            {{ $groupProduct->name }}
            <span>{{ $productItems->count() }} phân loại</span>
        </div>
        @foreach($productItems as $item)
        <div class="cart-row" data-product-id="{{ $item['variant']->product_id }}">
            <!-- Checkbox -->
            <div class="cart-col-checkbox">
                <input type="checkbox" class="cart-checkbox" value="{{ $item['variant']->id }}" data-total="{{ $item['total'] }}" aria-label="Chọn {{ $item['variant']->product->name }}">
            </div>
            <!-- Ảnh sản phẩm -->
            <div class="cart-col-img">
                @php
                    $imgSrc = $item['variant']->image ?: ($item['variant']->product->image ?? '');
                    if ($imgSrc && !str_starts_with($imgSrc, 'http')) {
                        $imgSrc = asset('storage/' . $imgSrc);
                    } else {
                        $imgSrc = $imgSrc ?: 'https://via.placeholder.com/75';
                    }
                @endphp
                <img src="{{ $imgSrc }}" alt="{{ $item['variant']->product->name }}">
            </div>
            <!-- Tên sản phẩm & Phân loại kèm Popup chọn biến thể -->
            <div class="cart-col-info">
                <div class="prod-name">{{ $item['variant']->product->name }}</div>
                <div style="position: relative; display: inline-block;">
                    <div class="variant-dropdown-toggle" onclick="toggleVariantModal('{{ $item['variant']->id }}')">
                        Phân loại: Màu {{ $item['variant']->color }} - Size {{ $item['variant']->size }}
                        <span style="font-size: 10px;">▼</span>
                    </div>
                    <!-- Popup Modal danh sách biến thể -->
                    <div class="variant-modal" id="modal-{{ $item['variant']->id }}">
                        <div class="variant-option-group">
                            <label>Chọn phân loại khác:</label>
                            <div class="variant-chips">
                                @php
                                    $productVariants = $item['variant']->product->variants;
                                @endphp
                                @foreach($productVariants as $v)
                                    <form method="POST" action="{{ route('cart.replace-variant', $item['variant']) }}" class="variant-chip-form">
                                        @csrf
                                        <input type="hidden" name="new_variant_id" value="{{ $v->id }}">
                                        @php
                                            $cannotSelect = $v->id == $item['variant']->id || $v->stock < $item['quantity'];
                                        @endphp
                                        <button type="submit" class="chip {{ $v->id == $item['variant']->id ? 'active' : '' }}" {{ $cannotSelect ? 'disabled' : '' }} title="{{ $v->stock < $item['quantity'] ? 'Không đủ hàng trong kho' : '' }}">
                                        {{ $v->color }} / {{ $v->size }}{{ $v->stock < $item['quantity'] ? ' - Hết hàng' : '' }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px;">
                            <button type="button" class="btn" style="padding: 4px 12px; font-size: 12px;" onclick="toggleVariantModal('{{ $item['variant']->id }}')">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Đơn giá -->
            <div class="cart-col-price">
                {{ number_format($item['price']) }}đ
            </div>
            <!-- Form tăng giảm số lượng dạng nút bấm +/- -->
            <div class="cart-col-qty">
                <form method="POST" action="{{ route('cart.update', $item['variant']) }}" id="qty-form-{{ $item['variant']->id }}">
                    @csrf @method('PATCH')
                    <div class="qty-group">
                        <button type="button" class="qty-btn qty-decrease">-</button>
                        <input type="number" name="quantity" id="qty-input-{{ $item['variant']->id }}" class="qty-input" value="{{ $item['quantity'] }}" min="1" max="{{ $item['variant']->stock }}">
                        <button type="button" class="qty-btn qty-increase">+</button>
                    </div>
                </form>
            </div>
            <!-- Tổng tiền sản phẩm -->
            <div class="cart-col-total">
                {{ number_format($item['total']) }}đ
            </div>
            <!-- Xóa -->
            <div class="cart-col-action">
                <form method="POST" action="{{ route('cart.remove', $item['variant']) }}">
                    @csrf @method('DELETE')
                    <button class="btn-delete" type="submit">Xóa</button>
                </form>
            </div>
        </div>
        @endforeach
        </section>
        @endforeach
        <!-- Phần tổng kết cuối trang -->
        <div class="cart-footer">
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="{{ route('shop.home') }}" class="btn btn-light">Tiếp tục mua sắm</a>
            </div>
            <div style="display: flex; gap: 20px; align-items: center;">
                <div class="cart-total">Tổng thanh toán: <span id="selected-total">0</span> đ</div>
                <a href="{{ route('checkout') }}" id="checkout-link" class="btn">Thanh toán ngay</a>
            </div>
        </div>
    @endif
</div>

<!-- Script hỗ trợ tăng giảm số lượng & popup phân loại -->
<script>
    function toggleVariantModal(id) {
        let modal = document.getElementById('modal-' + id);
        document.querySelectorAll('.variant-modal').forEach(m => {
            if(m !== modal) m.classList.remove('active');
        });
        modal.classList.toggle('active');
    }

    function updateSelectedTotal() {
        const checkboxes = document.querySelectorAll('.cart-checkbox');
        const selectedIds = [];
        let total = 0;

        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                selectedIds.push(checkbox.value);
                total += Number(checkbox.dataset.total);
            }
        });

        const selectedTotal = document.getElementById('selected-total');
        const checkoutLink = document.getElementById('checkout-link');
        if (!selectedTotal || !checkoutLink) return;
        selectedTotal.textContent = total.toLocaleString('vi-VN');
        checkoutLink.href = selectedIds.length
            ? '{{ route('checkout') }}?' + new URLSearchParams(selectedIds.map(id => ['selected_items[]', id])).toString()
            : '{{ route('checkout') }}';
    }

    function syncCartTotals(container) {
        container.querySelectorAll('.cart-row').forEach(function (row) {
            const priceText = row.querySelector('.cart-col-price')?.textContent || '';
            const quantity = Number(row.querySelector('.qty-input')?.value) || 0;
            const price = Number(priceText.replace(/[^0-9]/g, '')) || 0;
            const total = price * quantity;
            const checkbox = row.querySelector('.cart-checkbox');
            const rowTotal = row.querySelector('.cart-col-total');
            if (checkbox) checkbox.dataset.total = String(total);
            if (rowTotal) rowTotal.textContent = total.toLocaleString('vi-VN') + 'đ';
        });
    }

    async function submitCartForm(form) {
        const container = document.querySelector('.cart-container');
        if (!container || container.classList.contains('is-loading')) return;

        const selectedVariants = new Set(
            Array.from(container.querySelectorAll('.cart-checkbox:checked'))
                .map(checkbox => checkbox.value)
        );
        const sourceRow = form.closest('.cart-row');
        const sourceWasSelected = sourceRow?.querySelector('.cart-checkbox')?.checked || false;
        const sourceProductId = sourceRow?.dataset.productId;
        const isReplacingVariant = form.classList.contains('variant-chip-form');
        const previousGroupVariants = new Set(
            Array.from(container.querySelectorAll(`.cart-row[data-product-id="${sourceProductId}"] .cart-checkbox`))
                .map(checkbox => checkbox.value)
        );

        container.classList.add('is-loading');
        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, text/html' }
            });
            if (!response.ok) {
                let message = 'Không thể cập nhật giỏ hàng.';
                if ((response.headers.get('content-type') || '').includes('application/json')) {
                    const errorData = await response.json();
                    message = Object.values(errorData.errors || {}).flat()[0] || errorData.message || message;
                }
                throw new Error(message);
            }

            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const newContainer = parsed.querySelector('.cart-container');
            if (!newContainer) throw new Error('Dữ liệu giỏ hàng không hợp lệ.');

            newContainer.querySelectorAll('.cart-row').forEach(row => {
                const checkbox = row.querySelector('.cart-checkbox');
                if (checkbox && selectedVariants.has(checkbox.value)) checkbox.checked = true;
            });
            if (isReplacingVariant && sourceWasSelected) {
                const replacement = Array.from(newContainer.querySelectorAll(`.cart-row[data-product-id="${sourceProductId}"] .cart-checkbox`))
                    .find(checkbox => !previousGroupVariants.has(checkbox.value));
                if (replacement) replacement.checked = true;
            }
            syncCartTotals(newContainer);
            container.replaceWith(newContainer);
            updateSelectedTotal();
        } catch (error) {
            container.classList.remove('is-loading');
            alert(error.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.cart-container form');
        if (!form) return;
        event.preventDefault();
        submitCartForm(form);
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.qty-decrease, .qty-increase');
        if (!button) return;
        const form = button.closest('form');
        const input = form.querySelector('.qty-input');
        const current = Number(input.value) || 1;
        const minimum = Number(input.min) || 1;
        const maximum = Number(input.max) || Number.MAX_SAFE_INTEGER;
        const next = button.classList.contains('qty-increase')
            ? Math.min(current + 1, maximum)
            : Math.max(current - 1, minimum);
        if (next === current) return;
        input.value = next;
        form.requestSubmit();
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('.cart-checkbox')) updateSelectedTotal();
        if (event.target.matches('.qty-input')) {
            const input = event.target;
            const minimum = Number(input.min) || 1;
            const maximum = Number(input.max) || Number.MAX_SAFE_INTEGER;
            input.value = Math.min(Math.max(Number(input.value) || minimum, minimum), maximum);
            input.form.requestSubmit();
        }
    });
    updateSelectedTotal();
</script>
@endsection