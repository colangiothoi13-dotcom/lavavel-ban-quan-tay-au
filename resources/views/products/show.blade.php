@extends('layouts.app')

@section('content')
<div class="product-detail-page">
    <!-- HEADER & NÚT QUAY LẠI -->
    <div class="content-header">
        <h2>Chi tiết sản phẩm</h2>
        <a href="{{ route('products.index') }}" class="btn-back">← Quay lại</a>
    </div>

    <!-- KHU VỰC THÔNG TIN ĐANG XEM (MẶC ĐỊNH LÀ SẢN PHẨM CHÍNH) -->
    <div class="section-title">
        <span id="display-title">Thông tin chung sản phẩm</span>
        <button id="btn-reset" class="btn-reset" style="display: none;" onclick="resetMainProduct()">↺ Xem lại SP chính</button>
    </div>
    
    <div class="main-info-box">
        <!-- Cột ảnh hiển thị -->
        <div class="image-column">
            @php
                $mainImg = $product->image
                    ? asset('storage/' . $product->image)
                    : 'https://via.placeholder.com/180x180?text=No+Image';
            @endphp
            <img id="main-img" src="{{ $mainImg }}" alt="{{ $product->name }}" class="detail-main-img" data-default="{{ $mainImg }}" onerror="this.onerror=null;this.src='https://via.placeholder.com/180x180?text=No+Image';">
        </div>

        <!-- Cột thông tin chi tiết -->
        <div class="info-column">
            <table class="info-table">
                <tr>
                    <td class="label-col">Tên hiển thị:</td>
                    <td class="value-col"><strong id="info-name" data-default="{{ $product->name }}">{{ $product->name }}</strong></td>
                </tr>
                <tr>
                    <td class="label-col">Danh mục / Thuộc tính:</td>
                    <td id="info-attr" data-default="{{ $product->category?->name ?? 'Chưa phân loại' }}">
                        {{ $product->category?->name ?? 'Chưa phân loại' }}
                    </td>
                </tr>
                <tr>
                    <td class="label-col">Giá hiển thị:</td>
                    <td id="info-price" class="price-text" data-default="{{ number_format($product->base_price, 0, ',', '.') }} đ">
                        {{ number_format($product->base_price, 0, ',', '.') }} đ
                    </td>
                </tr>
                <tr>
                    <td class="label-col">Số lượng tồn kho:</td>
                    <td id="info-stock" data-default="{{ number_format($product->variants->sum('stock')) }} sản phẩm">
                        <strong>{{ number_format($product->variants->sum('stock')) }} sản phẩm</strong>
                    </td>
                </tr>
                <tr>
                    <td class="label-col">Mô tả:</td>
                    <td id="info-desc" data-default="{{ $product->description ?: 'Không có mô tả' }}">
                        {{ $product->description ?: 'Không có mô tả' }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- BẢNG DANH SÁCH BIẾN THỂ -->
    <div class="section-title" style="margin-top: 25px;">
        Danh sách biến thể <span style="font-size: 13px; font-weight: normal; color: #6c757d;">(Bấm vào từng dòng để xem chi tiết phía trên)</span>
    </div>
    
    @if($product->variants->isNotEmpty())
        <table class="custom-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 15%;">Ảnh biến thể</th>
                    <th style="width: 25%;">Màu sắc</th>
                    <th class="text-center" style="width: 20%;">Size</th>
                    <th class="text-center" style="width: 20%;">Số lượng tồn</th>
                    <th style="width: 20%;">Giá riêng</th>
                </tr>
            </thead>
            <tbody>
                @foreach($product->variants as $variant)
                    @php
                        $variantImg = $variant->image 
                            ? asset('storage/' . $variant->image) 
                            : $mainImg;
                        $variantPrice = $variant->price 
                            ? number_format($variant->price, 0, ',', '.') . ' đ' 
                            : number_format($product->base_price, 0, ',', '.') . ' đ';
                    @endphp
                    <tr class="variant-row" 
                        data-img="{{ $variantImg }}"
                        data-color="{{ $variant->color }}"
                        data-size="{{ $variant->size }}"
                        data-stock="{{ $variant->stock }}"
                        data-price="{{ $variantPrice }}">
                        <td class="text-center">
                            @if($variant->image)
                                <img src="{{ asset('storage/' . $variant->image) }}" alt="{{ $variant->color }}" class="variant-thumb">
                            @else
                                <span style="color: #999; font-size: 12px;">Dùng ảnh chính</span>
                            @endif
                        </td>
                        <td><strong>{{ $variant->color }}</strong></td>
                        <td class="text-center"><span class="badge-size">Size {{ $variant->size }}</span></td>
                        <td class="text-center"><span class="badge-stock">{{ $variant->stock }}</span></td>
                        <td style="color: #28a745; font-weight: bold;">{{ $variantPrice }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-variant-box">
            Sản phẩm này chưa có biến thể nào.
        </div>
    @endif
</div>

<style>
    /* Header & Nút Quay lại */
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .content-header h2 { margin: 0; color: #2c3e50; font-size: 20px; }
    .btn-back {
        background-color: #6c757d;
        color: white !important;
        padding: 7px 15px;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        font-size: 13px;
    }

    /* Tiêu đề nhóm thông tin */
    .section-title {
        font-size: 16px;
        font-weight: bold;
        color: #343a40;
        margin-bottom: 10px;
        border-left: 4px solid #0d6efd;
        padding-left: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn-reset {
        background: #17a2b8;
        color: white;
        border: none;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
    }

    /* Bảng thông tin chính */
    .main-info-box {
        display: flex;
        gap: 20px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 15px;
    }
    .image-column { width: 180px; flex-shrink: 0; }
    .detail-main-img {
        width: 180px;
        height: 180px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ccc;
    }
    .info-column { flex: 1; }
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table td {
        padding: 8px 10px;
        font-size: 14px;
        border-bottom: 1px solid #e9ecef;
    }
    .info-table tr:last-child td { border-bottom: none; }
    .label-col { width: 150px; color: #6c757d; font-weight: bold; }
    .price-text { color: #0d6efd; font-weight: bold; font-size: 16px; }

    /* Bảng biến thể */
    .custom-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    .custom-table th, .custom-table td {
        border: 1px solid #dee2e6;
        padding: 10px 12px;
        font-size: 14px;
        vertical-align: middle;
    }
    .custom-table th { background-color: #343a40; color: white; font-weight: 600; }
    
    .variant-row { cursor: pointer; transition: background 0.2s; }
    .variant-row:hover { background-color: #e2e8f0 !important; }
    .variant-row.active-row { background-color: #d1e7dd !important; }

    .variant-thumb {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ddd;
    }

    .badge-size {
        background: #e2e8f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
    }
    .badge-stock {
        background: #fef3c7;
        color: #92400e;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 13px;
    }

    .empty-variant-box {
        padding: 15px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        border-radius: 4px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelectorAll('.variant-row');
        const mainImg = document.getElementById('main-img');
        const infoName = document.getElementById('info-name');
        const infoAttr = document.getElementById('info-attr');
        const infoPrice = document.getElementById('info-price');
        const infoStock = document.getElementById('info-stock');
        const displayTitle = document.getElementById('display-title');
        const btnReset = document.getElementById('btn-reset');

        rows.forEach(row => {
            row.addEventListener('click', function () {
                // Đổi active row
                rows.forEach(r => r.classList.remove('active-row'));
                this.classList.add('active-row');

                // Lấy dữ liệu từ row
                const img = this.dataset.img;
                const color = this.dataset.color;
                const size = this.dataset.size;
                const stock = this.dataset.stock;
                const price = this.dataset.price;

                // Đưa lên bảng thông tin chính
                mainImg.src = img;
                infoName.textContent = infoName.dataset.default + " (" + color + ")";
                infoAttr.innerHTML = "Màu: <strong>" + color + "</strong> | Size: <strong>" + size + "</strong>";
                infoPrice.textContent = price;
                infoStock.innerHTML = "<strong>" + stock + "</strong> sản phẩm";

                displayTitle.textContent = "Chi tiết biến thể: " + color + " - Size " + size;
                btnReset.style.display = "inline-block";
            });
        });
    });

    function resetMainProduct() {
        const mainImg = document.getElementById('main-img');
        const infoName = document.getElementById('info-name');
        const infoAttr = document.getElementById('info-attr');
        const infoPrice = document.getElementById('info-price');
        const infoStock = document.getElementById('info-stock');
        const displayTitle = document.getElementById('display-title');
        const btnReset = document.getElementById('btn-reset');

        document.querySelectorAll('.variant-row').forEach(r => r.classList.remove('active-row'));

        mainImg.src = mainImg.dataset.default;
        infoName.textContent = infoName.dataset.default;
        infoAttr.textContent = infoAttr.dataset.default;
        infoPrice.textContent = infoPrice.dataset.default;
        infoStock.textContent = infoStock.dataset.default;

        displayTitle.textContent = "Thông tin chung sản phẩm";
        btnReset.style.display = "none";
    }
</script>
@endsection