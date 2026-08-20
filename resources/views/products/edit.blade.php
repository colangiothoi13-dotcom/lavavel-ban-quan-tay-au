@extends('layouts.app')

@section('content')
<div class="edit-product-page">
    @if($errors->any())
        <div class="form-errors" id="form-errors">
            <strong>Chưa thể cập nhật sản phẩm:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <!-- HEADER TRANG -->
    <div class="content-header">
        <h2>Sửa sản phẩm</h2>
        <a href="{{ route('products.index') }}" class="btn-cancel">Hủy & Quay lại</a>
    </div>

    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- KHỐI 1: THÔNG TIN CƠ BẢN -->
        <div class="section-title">Thông tin cơ bản</div>
        <div class="form-card">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tên sản phẩm <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Danh mục</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- Chọn danh mục --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Dành cho <span class="required">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="unisex" @selected(old('gender', $product->gender) === 'unisex')>Nam và nữ</option>
                        <option value="male" @selected(old('gender', $product->gender) === 'male')>Nam</option>
                        <option value="female" @selected(old('gender', $product->gender) === 'female')>Nữ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Giá chung (đ) <span class="required">*</span></label>
                    <input type="number" name="base_price" class="form-control" value="{{ old('base_price', $product->base_price) }}" required>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Mô tả sản phẩm</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Ảnh sản phẩm chính</label>
                    <div class="image-upload-wrapper">
                        <input type="file" name="image" class="file-input" accept="image/*">
                        @if($product->image)
                            <div class="image-preview">
                                <img src="{{ asset('storage/' . $product->image) }}" alt="Main Image" class="preview-thumb">
                                <span>Ảnh hiện tại</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI 2: BIẾN THỂ HIỆN TẠI (DẠNG BẢNG) -->
        <div class="section-title" style="margin-top: 25px;">Biến thể hiện tại</div>
        @if($product->variants->isNotEmpty())
            <table class="custom-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 15%;">Ảnh</th>
                        <th style="width: 25%;">Màu sắc</th>
                        <th style="width: 20%;">Size</th>
                        <th style="width: 20%;">Số lượng tồn</th>
                        <th style="width: 20%;">Thay ảnh mới</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($product->variants as $variant)
                        <tr>
                            <td class="text-center">
                                @if($variant->image)
                                    <img src="{{ asset('storage/' . $variant->image) }}" alt="" class="variant-thumb">
                                @else
                                    <span style="color: #999; font-size: 12px;">Chưa có</span>
                                @endif
                            </td>
                            <td>
                                <input type="text" name="variants[{{ $variant->id }}][color]" class="form-control" value="{{ old('variants.' . $variant->id . '.color', $variant->color) }}">
                            </td>
                            <td>
                                <input type="text" name="variants[{{ $variant->id }}][size]" class="form-control" value="{{ old('variants.' . $variant->id . '.size', $variant->size) }}">
                            </td>
                            <td>
                                <input type="number" name="variants[{{ $variant->id }}][stock]" class="form-control" value="{{ old('variants.' . $variant->id . '.stock', $variant->stock) }}">
                            </td>
                            <td>
                                <input type="file" name="variants[{{ $variant->id }}][image]" class="file-input-sm" accept="image/*">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-box">Sản phẩm chưa có biến thể nào.</div>
        @endif

        <!-- KHỐI 3: THÊM BIẾN THỂ MỚI -->
        <div class="section-title" style="margin-top: 25px;">Thêm biến thể mới</div>
        <div class="form-card">
            <div id="new-variant-list"></div>
            <button type="button" id="add-new-variant" class="btn-add-variant">+ Thêm dòng biến thể mới</button>
        </div>

        <!-- NÚT LƯU -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">Lưu Cập Nhật</button>
            <a href="{{ route('products.index') }}" class="btn-cancel">Hủy bỏ</a>
        </div>
    </form>
</div>

<style>
    /* Header & Tiêu đề */
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .content-header h2 { margin: 0; color: #2c3e50; font-size: 20px; }
    .form-errors { margin-bottom: 18px; padding: 12px 15px; border: 1px solid #f5c2c7; border-radius: 4px; background: #f8d7da; color: #842029; font-size: 13px; }
    .form-errors ul { margin: 7px 0 0 18px; }

    .section-title {
        font-size: 15px;
        font-weight: bold;
        color: #343a40;
        margin-bottom: 10px;
        border-left: 4px solid #0d6efd;
        padding-left: 10px;
    }

    /* Form Card Container */
    .form-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 18px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }

    .full-width { grid-column: span 3; }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .form-label {
        font-size: 13px;
        font-weight: bold;
        color: #495057;
    }

    .required { color: #dc3545; }

    .form-control {
        padding: 8px 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 13px;
        outline: none;
        width: 100%;
        box-sizing: border-box;
    }
    .form-control:focus { border-color: #0d6efd; }

    /* Upload ảnh */
    .image-upload-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .preview-thumb, .variant-thumb {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ccc;
    }
    .image-preview {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #6c757d;
    }

    /* Bảng biến thể */
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #dee2e6;
        padding: 8px 12px;
        font-size: 13px;
        vertical-align: middle;
    }
    .custom-table th {
        background-color: #343a40;
        color: white;
        font-weight: 600;
    }
    .custom-table tr:nth-child(even) { background-color: #f8f9fa; }
    .text-center { text-align: center; }

    /* File input nhỏ gọn */
    .file-input-sm { font-size: 12px; }

    /* Thêm biến thể mới */
    .new-variant-item {
        display: grid;
        grid-template-columns: 2fr 2fr 1fr 2fr auto;
        gap: 10px;
        align-items: center;
        background: white;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .btn-add-variant {
        background: #17a2b8;
        color: white;
        border: none;
        padding: 8px 14px;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
        font-weight: bold;
    }
    .btn-add-variant:hover { background: #138496; }

    .btn-remove-row {
        background: #dc3545;
        color: white;
        border: none;
        padding: 6px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }

    /* Nút hành động chính */
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }
    .btn-submit {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 9px 20px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
    }
    .btn-submit:hover { background-color: #218838; }

    .btn-cancel {
        background-color: #6c757d;
        color: white !important;
        text-decoration: none;
        padding: 9px 18px;
        border-radius: 4px;
        font-size: 14px;
        display: inline-block;
    }
    .btn-cancel:hover { background-color: #5a6268; }

    .empty-box {
        padding: 12px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        font-size: 13px;
        border-radius: 4px;
    }
</style>

<script>
    let newVariantIndex = 0;

    function addNewVariantRow() {
        const list = document.getElementById('new-variant-list');
        const row = document.createElement('div');
        row.className = 'new-variant-item';
        row.innerHTML = `
            <div>
                <input type="text" name="new_variants[${newVariantIndex}][color]" class="form-control" placeholder="Màu sắc (Đen/Ghi...)">
            </div>
            <div>
                <input type="text" name="new_variants[${newVariantIndex}][size]" class="form-control" placeholder="Size (29/30/M...)">
            </div>
            <div>
                <input type="number" name="new_variants[${newVariantIndex}][stock]" class="form-control" value="10" placeholder="Số lượng">
            </div>
            <div>
                <input type="file" name="new_variants[${newVariantIndex}][image]" class="file-input-sm" accept="image/*">
            </div>
            <div>
                <button type="button" class="btn-remove-row remove-new-variant">Xóa</button>
            </div>
        `;

        row.querySelector('.remove-new-variant').addEventListener('click', function () {
            row.remove();
        });

        list.appendChild(row);
        newVariantIndex++;
    }

    document.getElementById('add-new-variant').addEventListener('click', function () {
        addNewVariantRow();
    });
</script>
@endsection