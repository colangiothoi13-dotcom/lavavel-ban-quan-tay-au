@extends('layouts.shop', ['title' => $product->name])
@section('content')

@php
	$variant = $product->variants->first();
	$image = $product->image ? asset('storage/'.$product->image) : ($variant?->image ? asset('storage/'.$variant->image) : 'https://via.placeholder.com/600x600?text=No+Image');
	$selectedVariant = $selectedVariant ?? null;
	$selectedImage = $selectedVariant?->image ? asset('storage/'.$selectedVariant->image) : $image;

	// Xử lý dữ liệu biến thể ở đây để tránh lỗi Blade parser
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

<div class="detail">
	<div>
		<!-- Thêm id="main-image" để JS có thể gọi và đổi ảnh -->
		<img id="main-image" src="{{ $selectedImage }}" alt="{{ $product->name }}" onerror="this.onerror=null;this.src='https://via.placeholder.com/600x600?text=No+Image';">
	</div>
	<div>
		<h1>{{ $product->name }}</h1>
		<p class="audience">{{ ['male' => 'Dành cho nam', 'female' => 'Dành cho nữ', 'unisex' => 'Dành cho nam và nữ'][$product->gender] ?? 'Dành cho nam và nữ' }}</p>
		<p>{{ $product->description ?: 'Sản phẩm quần Tây Âu chất lượng cao.' }}</p>

		<h2 class="variants-title">Chọn biến thể</h2>
		@if($product->variants->isEmpty())
			<p class="empty-variants">Sản phẩm chưa có biến thể.</p>
		@else
			@php($colors = $product->variants->pluck('color')->filter()->unique()->values())
			@php($sizes = $product->variants->pluck('size')->filter()->unique()->values())
			
			<div class="choice-group">
				<strong>Màu sắc</strong>
				<div class="choice-list" id="color-choices">
					@foreach($colors as $color)
						<button type="button" class="choice" data-color="{{ $color }}">{{ $color }}</button>
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
			</div>
			
			<p id="choice-message" class="choice-message">Hãy chọn màu sắc và size.</p>
		@endif

		<form action="{{ route('cart.add', $product) }}" method="POST" class="purchase-form">
			@csrf
			<input type="hidden" name="variant_id" id="selected-variant-id" value="{{ $selectedVariant?->id }}">
			<div class="quantity-row">
				<label for="quantity">Số lượng</label>
				<input id="quantity" type="number" name="quantity" min="1" value="1">
			</div>
			<button type="submit" id="add-to-cart" class="add-to-cart" disabled>Chọn màu và size để mua</button>
		</form>
	</div>
</div>

<style>
	.variants-title { font-size: 19px; margin: 25px 0 12px; color: #20252b; }
	.audience { display: inline-block; margin: 0 0 8px; padding: 4px 9px; background: #eef2ff; color: #3730a3; border-radius: 4px; font-size: 13px; font-weight: 700; }
	.choice-group { margin: 14px 0; }
	.choice-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
	.choice { border: 1px solid #ccd1d5; background: #fff; border-radius: 4px; padding: 8px 14px; cursor: pointer; }
	.choice.active, .choice:hover { border-color: #b45309; background: #fff7ed; color: #9a3412; }
	.choice-message { font-size: 13px; color: #6b7280; margin: 8px 0 14px; font-weight: 500; }
	.in-stock { color: #166534; } 
	.out-stock { color: #991b1b; } 
	.empty-variants { color: #6b7280; }
	.purchase-form { margin-top: 24px; padding-top: 18px; border-top: 1px solid #e1e5e8; }
	.quantity-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; font-weight: 700; }
	.quantity-row input { width: 80px; padding: 9px; border: 1px solid #ccd1d5; border-radius: 4px; }
	.add-to-cart { width: 100%; padding: 12px; border: 0; border-radius: 4px; background: #b45309; color: #fff; font-weight: 700; cursor: pointer; transition: 0.2s; }
	.add-to-cart:disabled { background: #9ca3af; cursor: not-allowed; }
</style>

<script>
	// 1. Chuyển dữ liệu Variants từ biến PHP đã xử lý ở trên
	const variants = @json($variantsData);

	const defaultImage = @json($image);
	const mainImageEl = document.getElementById('main-image');

	let selectedColor = @json($selectedVariant?->color);
	let selectedSize = @json($selectedVariant?->size);

	const refreshChoices = () => {
		// Cập nhật class 'active' cho các nút bấm
		document.querySelectorAll('[data-color]').forEach(button => button.classList.toggle('active', button.dataset.color === selectedColor));
		document.querySelectorAll('[data-size]').forEach(button => button.classList.toggle('active', button.dataset.size === selectedSize));

		// TÌM ẢNH: Chỉ cần có màu là đổi ảnh ngay (chưa cần size)
		const colorMatch = variants.find(v => v.color === selectedColor && v.image);
		mainImageEl.src = colorMatch ? colorMatch.image : defaultImage;

		// KIỂM TRA ĐIỀU KIỆN MUA HÀNG: Phải khớp cả màu và size
		const match = variants.find(v => v.color === selectedColor && v.size === selectedSize);
		const button = document.getElementById('add-to-cart');
		const variantId = document.getElementById('selected-variant-id');
		const messageEl = document.getElementById('choice-message');

		if (!match) {
			messageEl.textContent = 'Hãy chọn màu sắc và size.';
			button.disabled = true;
			button.textContent = 'Chọn màu và size để mua';
			variantId.value = '';
			return;
		}

		const stock = Number(match.stock);
		variantId.value = match.id;
		button.disabled = stock < 1;
		button.textContent = stock > 0 ? 'Thêm vào giỏ hàng' : 'Hết hàng';
		messageEl.innerHTML = stock > 0 
			? `Đã chọn: <strong>${selectedColor} - Size ${selectedSize}</strong> (<span class="in-stock">Còn ${stock} sản phẩm</span>)` 
			: `<span class="out-stock">Tổ hợp này đã hết hàng.</span>`;
	};

	const choose = (type, value) => {
		if (type === 'color') selectedColor = value;
		if (type === 'size') selectedSize = value;
		refreshChoices();
	};

	document.querySelectorAll('[data-color]').forEach(button => button.addEventListener('click', () => choose('color', button.dataset.color)));
	document.querySelectorAll('[data-size]').forEach(button => button.addEventListener('click', () => choose('size', button.dataset.size)));
	
	refreshChoices();
</script>
@endsection