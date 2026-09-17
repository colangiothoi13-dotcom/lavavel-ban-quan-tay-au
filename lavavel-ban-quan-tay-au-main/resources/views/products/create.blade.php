@extends('layouts.app')

@section('content')
<div class="create-product-page">
    @if($errors->any())
        <div class="form-errors" id="form-errors">
            <strong>Chưa thể lưu sản phẩm:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <!-- HEADER TRANG -->
    <div class="content-header">
        <h2>Thêm Quần Tây Âu Mới</h2>
        <a href="{{ route('products.index') }}" class="btn-cancel">Hủy & Quay lại</a>
    </div>

    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- KHỐI 1: THÔNG TIN CƠ BẢN -->
        <div class="section-title">Thông tin cơ bản</div>
        <div class="form-card">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tên sản phẩm <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Tên sản phẩm" value="{{ old('name') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Danh mục</label>
                    <select name="category_id" class="form-control">
                        <option value="">Chọn danh mục</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Dành cho <span class="required">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="unisex" @selected(old('gender', 'unisex') === 'unisex')>Nam và nữ</option>
                        <option value="male" @selected(old('gender') === 'male')>Nam</option>
                        <option value="female" @selected(old('gender') === 'female')>Nữ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Giá chung (đ) <span class="required">*</span></label>
                    <input type="number" name="base_price" class="form-control" placeholder="Giá Tiền" value="{{ old('base_price') }}" required>
                    <small class="generate-note">Tất cả màu và size của sản phẩm sẽ dùng cùng mức giá này.</small>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Mô tả sản phẩm</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Mô tả">{{ old('description') }}</textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Ảnh sản phẩm chính</label>
                    <div class="image-upload-wrapper">
                        <input type="file" id="product_image" name="image" class="file-input" accept="image/*">
                        <div id="product-preview-container" class="image-preview" style="display: none;">
                            <img id="product-image-preview" src="" alt="Preview sản phẩm chính" class="preview-thumb">
                            <span>Ảnh đã chọn</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI 2: TẠO TỔ HỢP THUỘC TÍNH -->
        <div class="section-title" style="margin-top: 25px;">
            <span>Thuộc tính biến thể</span>
        </div>

        <div class="form-card">
            <div class="attribute-grid">
                <div class="form-group">
                    <label class="form-label">Màu sắc và ảnh dùng chung <span class="required">*</span></label>
                    <div id="color-inputs"></div>
                    <button type="button" class="btn-add-row" id="add-color">+ Thêm màu</button>
                </div>
                <div class="form-group">
                    <label class="form-label">Kích thước / Size <span class="required">*</span></label>
                    <div id="size-inputs"></div>
                    <button type="button" class="btn-add-row" id="add-size">+ Thêm size</button>
                </div>
            </div>
            <button type="button" id="generate-variants" class="btn-generate">Generate tổ hợp biến thể</button>
            <p class="generate-note">Ví dụ: 2 màu và 2 size sẽ tạo 4 dòng. Ảnh được dùng chung theo màu; giá dùng chung theo sản phẩm.</p>
            <div id="variant-list"></div>
        </div>

        <!-- NÚT LƯU -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">Lưu Sản Phẩm</button>
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
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Form Container */
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
    .attribute-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .attribute-input { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: 8px; margin-bottom: 8px; align-items: center; }
    .attribute-input .form-control { flex: 1; }
    .btn-generate { margin-top: 18px; background: #0d6efd; color: #fff; border: 0; padding: 9px 14px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .generate-note { color: #6c757d; font-size: 13px; margin: 10px 0 16px; }

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
    .preview-thumb {
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

    /* Thêm biến thể mới */
    .variant-item {
        display: grid;
        grid-template-columns: 1.2fr 1fr auto;
        gap: 10px;
        align-items: center;
        background: white;
        padding: 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .btn-add-variant, .btn-add-row {
        background: #17a2b8;
        color: white;
        border: none;
        padding: 6px 14px;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
        font-weight: bold;
    }
    .btn-add-variant:hover, .btn-add-row:hover { background: #138496; }

    .btn-remove-row {
        background: #dc3545;
        color: white;
        border: none;
        padding: 6px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }

    .file-input-sm { font-size: 12px; }

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
</style>

<script>
    function setupImagePreview(inputEl, containerEl, previewImgEl) {
        if (!inputEl || !previewImgEl) return;

        inputEl.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                previewImgEl.src = '';
                if (containerEl) containerEl.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewImgEl.src = e.target.result;
                if (containerEl) containerEl.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        });
    }

    const mainInput = document.getElementById('product_image');
    const mainContainer = document.getElementById('product-preview-container');
    const mainPreview = document.getElementById('product-image-preview');
    setupImagePreview(mainInput, mainContainer, mainPreview);

    let variantIndex = 0;
    let colorIndex = 0;
    let sizeIndex = 0;

    function addAttributeRow(type) {
        const index = type === 'color' ? colorIndex++ : sizeIndex++;
        const container = document.getElementById(type + '-inputs');
        const row = document.createElement('div');
        row.className = 'attribute-input';
        const inputName = `attribute_${type}s[${index}]`;
        const extra = type === 'color' ? `<input type="file" name="attribute_color_images[${index}]" class="file-input-sm" accept="image/*" title="Ảnh dùng chung cho màu này">` : '';
        row.innerHTML = `<input type="text" name="${inputName}" class="form-control attribute-value" placeholder="${type === 'color' ? 'Đen, Xám...' : 'S, M, L...'}" required>${extra}<button type="button" class="btn-remove-row">Xóa</button>`;
        row.querySelector('button').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    function addVariantRow(color, size) {
        const list = document.getElementById('variant-list');
        const row = document.createElement('div');
        row.className = 'variant-item';

        row.innerHTML = `
            <div>
                <label class="form-label">Màu sắc</label>
                <input type="text" name="variants[${variantIndex}][color]" class="form-control" value="${color}" readonly required>
            </div>
            <div>
                <label class="form-label">Size</label>
                <input type="text" name="variants[${variantIndex}][size]" class="form-control" value="${size}" readonly required>
            </div>
            <div>
                <label class="form-label">Số lượng</label>
                <input type="number" name="variants[${variantIndex}][stock]" class="form-control" value="0" min="0" required>
            </div>
            <div style="align-self: flex-end;">
                <button type="button" class="btn-remove-row remove-variant-btn">Xóa</button>
            </div>
        `;

        row.querySelector('.remove-variant-btn').addEventListener('click', function () {
            row.remove();
        });

        list.appendChild(row);
        variantIndex++;
    }

    function generateVariants() {
        const colors = [...document.querySelectorAll('#color-inputs .attribute-value')].map(input => input.value.trim()).filter(Boolean);
        const sizes = [...document.querySelectorAll('#size-inputs .attribute-value')].map(input => input.value.trim()).filter(Boolean);
        if (!colors.length || !sizes.length) { alert('Hãy nhập ít nhất một màu và một size.'); return; }
        document.getElementById('variant-list').innerHTML = '';
        variantIndex = 0;
        colors.forEach(color => sizes.forEach(size => addVariantRow(color, size)));
    }
    document.getElementById('add-color').addEventListener('click', () => addAttributeRow('color'));
    document.getElementById('add-size').addEventListener('click', () => addAttributeRow('size'));
    document.getElementById('generate-variants').addEventListener('click', generateVariants);
    addAttributeRow('color');
    addAttributeRow('size');
    document.querySelector('form').addEventListener('submit', function (event) {
        if (!document.querySelector('#variant-list input[name^="variants["]')) {
            generateVariants();
        }
        if (!document.querySelector('#variant-list input[name^="variants["]')) {
            event.preventDefault();
            document.getElementById('variant-list').scrollIntoView({ behavior: 'smooth' });
        }
    });
</script>
@endsection