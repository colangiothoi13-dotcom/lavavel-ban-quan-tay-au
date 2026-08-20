@extends('layouts.app')

@section('content')
<div class="product-page">
    
    <!-- HEADER TRANG -->
    <div class="content-header">
        <h2>Sản phẩm quần Tây Âu</h2>
        <a href="{{ route('products.create') }}" class="btn-add">+ Thêm Sản Phẩm Mới</a>
    </div>

    <!-- BỘ LỌC NẰM NGANG TRÊN BẢNG -->
    <form method="GET" action="{{ route('products.index') }}" class="filter-box">
        <input type="text" name="keyword" class="form-control" placeholder="Tìm theo tên..." value="{{ request('keyword') }}">
        
        <select name="size" class="form-control">
            <option value="">-- Chọn Size --</option>
            @foreach(['29', '30', '31', '32', '33', 'S', 'M', 'L', 'XL'] as $s)
                <option value="{{ $s }}" {{ request('size') == $s ? 'selected' : '' }}>Size {{ $s }}</option>
            @endforeach
        </select>

        <select name="gender" class="form-control">
            <option value="">-- Nam / Nữ --</option>
            <option value="male" @selected(request('gender') === 'male')>Đồ nam</option>
            <option value="female" @selected(request('gender') === 'female')>Đồ nữ</option>
            <option value="unisex" @selected(request('gender') === 'unisex')>Nam và nữ</option>
        </select>

        <input type="number" name="min_price" class="form-control" placeholder="Giá từ..." value="{{ request('min_price') }}">
        
        <input type="number" name="max_price" class="form-control" placeholder="Giá đến..." value="{{ request('max_price') }}">

        <select name="sort_by" class="form-control">
            <option value="">Sắp xếp theo</option>
            <option value="name_asc" {{ request('sort_by') == 'name_asc' ? 'selected' : '' }}>Tên (A-Z)</option>
            <option value="name_desc" {{ request('sort_by') == 'name_desc' ? 'selected' : '' }}>Tên (Z-A)</option>
            <option value="price_asc" {{ request('sort_by') == 'price_asc' ? 'selected' : '' }}>Giá tăng dần</option>
            <option value="price_desc" {{ request('sort_by') == 'price_desc' ? 'selected' : '' }}>Giá giảm dần</option>
        </select>

        <button type="submit" class="btn-filter">Lọc</button>
    </form>

    <!-- BẢNG DỰ LIỆU -->
    <table class="custom-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">ID</th>
                <th class="text-center" style="width: 12%;">Hình ảnh</th>
                <th style="width: 25%;">Tên sản phẩm</th>
                <th style="width: 15%;">Giá cơ bản</th>
                <th style="width: 23%;">Biến thể</th>
                <th class="text-center" style="width: 20%;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $imageUrl = $product->image
                        ? asset('storage/' . $product->image)
                        : ($firstVariant && $firstVariant->image
                            ? asset('storage/' . $firstVariant->image)
                            : 'https://via.placeholder.com/80x80?text=No+Image');
                @endphp
                <tr>
                    <td class="text-center"><strong>{{ $product->id }}</strong></td>
                    <td class="text-center">
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="product-img" onerror="this.onerror=null;this.src='https://via.placeholder.com/80x80?text=No+Image';">
                    </td>
                    <td>
                        <strong class="product-name-toggle" data-target="variant-{{ $product->id }}">
                            {{ $product->name }}
                        </strong><br><small>{{ ['male' => 'Đồ nam', 'female' => 'Đồ nữ', 'unisex' => 'Nam và nữ'][$product->gender] ?? 'Nam và nữ' }}</small>
                    </td>
                    <td style="color: #0d6efd; font-weight: bold;">{{ number_format($product->base_price) }} đ</td>
                    <td>
                        @if($product->variants->isNotEmpty())
                            <button type="button" class="btn-variant-toggle" data-target="variant-{{ $product->id }}">
                                Xem biến thể
                            </button>
                            <div class="variant-list" id="variant-{{ $product->id }}" style="display: none;">
                                @foreach($product->variants as $variant)
                                    <div class="variant-tag">
                                        <span>{{ $variant->color }}</span> - 
                                        <span>Size {{ $variant->size }}</span> - 
                                        <span>SL: {{ $variant->stock }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <span style="color: #888;">Chưa có biến thể</span>
                        @endif
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="{{ route('products.show', $product->id) }}" class="btn btn-show">Xem</a>
                            <a href="{{ route('products.edit', $product->id) }}" class="btn btn-edit">Sửa</a>
                            <form action="{{ route('products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');" style="margin: 0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-delete">Xóa</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="color: #6c757d; padding: 20px;">
                        Chưa có sản phẩm nào.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 15px;">
        {{ $products->links() }}
    </div>
</div>

<style>
    /* Tiêu đề & Nút thêm */
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .content-header h2 { margin: 0; color: #2c3e50; font-size: 20px; }
    .btn-add {
        background-color: #28a745;
        color: white;
        padding: 9px 16px;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        font-size: 14px;
    }
    .btn-add:hover { background-color: #218838; }

    /* Thanh bộ lọc nằm ngang */
    .filter-box {
        display: flex;
        gap: 10px;
        background-color: #f8f9fa;
        padding: 12px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .form-control {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 13px;
        outline: none;
        flex: 1;
        min-width: 120px;
    }
    .btn-filter {
        background-color: #0d6efd;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
    }
    .btn-filter:hover { background-color: #0b5ed7; }

    /* Bảng sản phẩm chuẩn định dạng */
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #dee2e6;
        padding: 10px 12px;
        text-align: left;
        font-size: 14px;
        vertical-align: middle;
    }
    .custom-table th {
        background-color: #343a40;
        color: white;
        font-weight: 600;
    }
    .custom-table tr:nth-child(even) { background-color: #f8f9fa; }
    .custom-table tr:hover { background-color: #f1f3f5; }

    .text-center { text-align: center; }

    /* Ảnh sản phẩm */
    .product-img {
        width: 65px;
        height: 65px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ddd;
    }

    .product-name-toggle {
        color: #0d6efd;
        cursor: pointer;
    }
    .product-name-toggle:hover { text-decoration: underline; }

    /* Biến thể */
    .btn-variant-toggle {
        background: #e9ecef;
        border: 1px solid #ced4da;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        cursor: pointer;
    }
    .variant-list { margin-top: 8px; }
    .variant-tag {
        font-size: 12px;
        background: #e2e8f0;
        padding: 3px 6px;
        border-radius: 4px;
        margin-bottom: 4px;
    }

    /* Các nút Thao tác */
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 8px;
        align-items: center;
    }
    .btn {
        padding: 6px 12px;
        text-decoration: none;
        border-radius: 4px;
        font-size: 13px;
        border: none;
        cursor: pointer;
        color: white !important;
        display: inline-block;
    }
    .btn-show { background-color: #17a2b8; }
    .btn-edit { background-color: #ffc107; color: #212529 !important; }
    .btn-delete { background-color: #dc3545; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-variant-toggle, .product-name-toggle').forEach(function (el) {
            el.addEventListener('click', function () {
                const targetId = this.dataset.target;
                const target = document.getElementById(targetId);
                if (!target) return;

                const isHidden = target.style.display === 'none';
                target.style.display = isHidden ? 'block' : 'none';

                const btn = this.closest('td')?.querySelector('.btn-variant-toggle');
                if (btn) {
                    btn.textContent = isHidden ? 'Ẩn biến thể' : 'Xem biến thể';
                }
            });
        });
    });
</script>
@endsection