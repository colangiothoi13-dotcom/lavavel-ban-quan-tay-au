@extends('layouts.app')
@section('content')
<style>
    /* Mở rộng khung chứa giỏ hàng ra toàn bộ không gian trống bên trái */
    .cart-container { 
        color: #334155; 
        max-width: 100% !important; 
        width: 100%;
        margin: 0; 
        padding: 0 20px; 
        position: relative; 
    }
    .cart-container h2 { font-size: 24px; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px; }
    /* Cart Item Row */
    .cart-row {
        display: flex;
        align-items: center;
        padding: 20px 15px;
        background: #fff;
        border-bottom: 1px solid #f1f5f9;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
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
    .cart-footer { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .cart-total { font-size: 18px; font-weight: bold; color: #ee4d2d; }
    .btn { padding: 10px 24px; background: #ee4d2d; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-weight: 500; font-size: 14px; transition: 0.2s; }
    .btn:hover { background: #d73211; }
    .btn-light { background: #e2e8f0; color: #475569; }
    .btn-light:hover { background: #cbd5e1; }
    .empty-cart { padding: 40px; text-align: center; background: #fff; border-radius: 8px; color: #64748b; }
    .variant-chip-form { display: inline-block; margin: 0; }
    .chip { font-family: inherit; }
    
</style>

<div class="cart-container">
    <h2>Giỏ hàng của bạn</h2>
    @if($items->isEmpty())
        <div class="empty-cart">
            Giỏ hàng đang trống. <a href="{{ route('shop.home') }}" style="color: #2563eb; text-decoration: none; font-weight: bold;">Tiếp tục mua sắm &rarr;</a>
        </div>
    @else
        @foreach($items as $item)
        <div class="cart-row">
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
                                        <button type="submit" class="chip {{ $v->id == $item['variant']->id ? 'active' : '' }}" {{ $v->id == $item['variant']->id ? 'disabled' : '' }}>
                                        {{ $v->color }} / {{ $v->size }}
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
                        <button type="button" class="qty-btn" onclick="decreaseQty('{{ $item['variant']->id }}')">-</button>
                        <input type="number" name="quantity" id="qty-input-{{ $item['variant']->id }}" class="qty-input" value="{{ $item['quantity'] }}" min="1" max="{{ $item['variant']->stock }}" onchange="this.form.submit()">
                        <button type="button" class="qty-btn" onclick="increaseQty('{{ $item['variant']->id }}', {{ $item['variant']->stock }})">+</button>
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
    function decreaseQty(id) {
        let input = document.getElementById('qty-input-' + id);
        let val = parseInt(input.value);
        if (val > 1) {
            input.value = val - 1;
            document.getElementById('qty-form-' + id).submit();
        }
    }
    function increaseQty(id, maxStock) {
        let input = document.getElementById('qty-input-' + id);
        let val = parseInt(input.value);
        if (val < maxStock) {
            input.value = val + 1;
            document.getElementById('qty-form-' + id).submit();
        }
    }
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

        document.getElementById('selected-total').textContent = total.toLocaleString('vi-VN');
        const checkoutLink = document.getElementById('checkout-link');
        checkoutLink.href = selectedIds.length
            ? '{{ route('checkout') }}?' + new URLSearchParams(selectedIds.map(id => ['selected_items[]', id])).toString()
            : '{{ route('checkout') }}';
    }

    document.querySelectorAll('.cart-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectedTotal);
    });
    updateSelectedTotal();
</script>
@endsection