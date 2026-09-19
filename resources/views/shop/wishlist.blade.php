@extends('layouts.app')

@section('content')
<div style="max-width:1200px; margin:0 auto; padding:24px 16px 50px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
        <div>
            <p style="margin:0 0 8px; color:#f97316; font-size:12px; text-transform:uppercase; font-weight:700; letter-spacing:.08em;">Cá nhân hóa</p>
            <h1 style="margin:0; color:#1f2937; font-size:32px;">Danh sách yêu thích</h1>
        </div>
        <a href="{{ route('shop.home') }}" style="text-decoration:none; color:#334155; font-weight:700;">← Quay lại cửa hàng</a>
    </div>

    @if($products->isEmpty())
        <p style="color:#64748b; font-style:italic;">Bạn chưa lưu sản phẩm yêu thích nào.</p>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px;">
            @foreach($products as $product)
                @php
                    $variant = $product->variants->first();
                    $image = $product->image ? asset('storage/'.$product->image) : ($variant?->image ? asset('storage/'.$variant->image) : 'https://via.placeholder.com/500x600?text=No+Image');
                @endphp
                <article style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; display:flex; flex-direction:column;">
                    <a href="{{ route('shop.products.show', $product) }}" style="display:block;">
                        <img src="{{ $image }}" alt="{{ $product->name }}" style="width:100%; height:260px; object-fit:cover; display:block;">
                    </a>
                    <div style="padding:14px 14px 10px;">
                        <h3 style="margin:0 0 6px; font-size:16px;">{{ $product->name }}</h3>
                        <div style="color:#b45309; font-weight:700; font-size:18px;">{{ number_format($variant?->price ?: $product->base_price, 0, ',', '.') }} đ</div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:0 14px 14px; margin-top:auto;">
                        <a href="{{ route('shop.products.show', $product) }}" style="color:#334155; font-weight:700;">Xem sản phẩm</a>
                        <form method="POST" action="{{ route('shop.wishlist.toggle', $product) }}" style="margin:0;">
                            @csrf
                            <button type="submit" style="padding:9px 12px; border:1px solid #fecdd3; border-radius:6px; background:#fff1f2; color:#be123c; font-weight:700; cursor:pointer;">Bỏ yêu thích</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
