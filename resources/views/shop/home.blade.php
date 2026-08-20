@extends($layout ?? 'layouts.shop', ['title' => 'Trang chủ'])
@section('content')
<style>
    /* Tone màu chủ đạo: Xanh Navy (#1e293b) và Vàng Gold (#b4860b) */
    .shop-home { color: #334155; font-family: system-ui, -apple-system, sans-serif; }
    
    /* Hero Banner & Thanh tìm kiếm trung tâm */
    .hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 50px 20px; text-align: center; border-radius: 12px; margin-bottom: 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .hero h1 { margin: 0 0 12px; font-size: 34px; font-weight: 700; color: #f8fafc; letter-spacing: 0.5px; }
    .hero p { font-size: 16px; color: #94a3b8; max-width: 600px; margin: 0 auto 24px; line-height: 1.5; }
    
    .hero-search { display: flex; justify-content: center; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.2); border-radius: 30px;}
    .hero-search input { flex: 1; padding: 15px 24px; border: none; border-radius: 30px 0 0 30px; font-size: 15px; outline: none; color: #1e293b; }
    .hero-search button { padding: 15px 32px; background: #b4860b; color: #fff; border: none; border-radius: 0 30px 30px 0; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; }
    .hero-search button:hover { background: #9c6f19; }

    /* Layout & Sidebar */
    .shop-layout { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 30px; }
    .shop-sidebar { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; align-self: start; position: sticky; top: 22px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .shop-sidebar h3 { margin: 0 0 16px; font-size: 17px; color: #1e293b; border-left: 4px solid #b4860b; padding-left: 10px; }
    
    .filter-group { border-bottom: 1px solid #f1f5f9; padding-bottom: 18px; margin-bottom: 18px; }
    .filter-group label { display: block; font-size: 13px; font-weight: 700; margin: 12px 0 6px; color: #475569; }
    .filter-group input, .filter-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; transition: 0.2s; }
    .filter-group input:focus, .filter-group select:focus { border-color: #b4860b; }
    
    .filter-submit { width: 100%; background: #1e293b; color: #fff; border: 0; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 10px; }
    .filter-submit:hover { background: #0f172a; }
    .clear-filter { display: block; color: #dc2626; text-decoration: none; text-align: center; margin-top: 12px; font-size: 13px; font-weight: 500; }
    
    .category-list { list-style: none; padding: 0; margin: 0; }
    .category-list li { margin: 10px 0; }
    .category-list a { color: #475569; text-decoration: none; font-size: 14px; transition: 0.2s; }
    .category-list a.active, .category-list a:hover { color: #b4860b; font-weight: 700; padding-left: 5px; }
    
    .account-box { padding-top: 8px; }
    .account-box a { display: block; color: #475569; text-decoration: none; margin: 12px 0; font-size: 14px; font-weight: 500;}
    .account-box a:hover { color: #b4860b; }

    /* Lưới sản phẩm */
    .shop-main h2 { color: #1e293b; font-size: 24px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0; margin-bottom: 24px; }
    .products { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; }
    .product-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; transition: all 0.3s ease; text-decoration: none; color: #1e293b; }
    .product-card:hover { box-shadow: 0 12px 24px rgba(0,0,0,0.08); transform: translateY(-4px); border-color: #cbd5e1; }
    .product-card img { width: 100%; height: 280px; object-fit: cover; background: #f8fafc; border-bottom: 1px solid #f1f5f9;}
    .product-card-body { padding: 18px; }
    .product-card h3 { margin: 0 0 8px; font-size: 16px; font-weight: 600; line-height: 1.4; }
    .product-card small { color: #64748b; font-size: 13px; }
    .price { font-weight: 700; color: #b4860b; font-size: 18px; margin-top: 8px; }
    
    .pagination { margin-top: 30px; }
    
    @media(max-width:800px) {
        .shop-layout { grid-template-columns: 1fr; }
        .shop-sidebar { position: static; }
        .hero { padding: 40px 15px; }
        .hero h1 { font-size: 26px; }
    }
</style>
<div class="shop-home">
@php($isAdmin = auth()->check() && auth()->user()->isAdmin())

<!-- Chuyển Hero Banner và Thanh tìm kiếm lên đầu (Full width) -->
<div class="hero">
    <p>Sự kết hợp hoàn hảo giữa chất liệu cao cấp và kĩ thuật may đo tỉ mỉ, tôn lên vẻ lịch lãm của bạn.</p>
    
    <!-- Thanh tìm kiếm đưa ra giữa -->
    <form class="hero-search" method="GET" action="{{ route('shop.home') }}">
        <!-- Giữ lại các bộ lọc khác nếu người dùng đang dùng bộ lọc ở sidebar -->
        @if(request('gender')) <input type="hidden" name="gender" value="{{ request('gender') }}"> @endif
        @if(request('size')) <input type="hidden" name="size" value="{{ request('size') }}"> @endif
        @if(request('min_price')) <input type="hidden" name="min_price" value="{{ request('min_price') }}"> @endif
        @if(request('max_price')) <input type="hidden" name="max_price" value="{{ request('max_price') }}"> @endif
        @if(request('sort_by')) <input type="hidden" name="sort_by" value="{{ request('sort_by') }}"> @endif
        @if(request('category_id')) <input type="hidden" name="category_id" value="{{ request('category_id') }}"> @endif
        
        <input type="text" name="keyword" placeholder="Nhập tên mẫu quần âu bạn tìm..." value="{{ request('keyword') }}">
        <button type="submit">Tìm kiếm</button>
    </form>
</div>

@if(!$isAdmin)
<div class="shop-layout">
<aside class="shop-sidebar">
    <form method="GET" action="{{ route('shop.home') }}">
        <!-- Ẩn input keyword ở đây để khi bấm lọc sidebar, từ khóa vẫn được giữ -->
        @if(request('keyword')) <input type="hidden" name="keyword" value="{{ request('keyword') }}"> @endif
        
        <div class="filter-group">
            <h3>Bộ lọc chi tiết</h3>
            
            <label>Dành cho</label>
            <select name="gender">
                <option value="">Nam và nữ</option>
                <option value="male" @selected(request('gender') === 'male')>Đồ nam</option>
                <option value="female" @selected(request('gender') === 'female')>Đồ nữ</option>
                <option value="unisex" @selected(request('gender') === 'unisex')>Nam và nữ</option>
            </select>
            
            <label>Kích thước</label>
            <select name="size">
                <option value="">Tất cả size</option>
                @foreach(['29','30','31','32','33','S','M','L','XL'] as $size)
                    <option value="{{ $size }}" @selected(request('size') === $size)>Size {{ $size }}</option>
                @endforeach
            </select>
            
            <label>Giá từ</label>
            <input type="number" name="min_price" min="0" value="{{ request('min_price') }}" placeholder="0">
            
            <label>Giá đến</label>
            <input type="number" name="max_price" min="0" value="{{ request('max_price') }}" placeholder="Không giới hạn">
            
            <label>Sắp xếp</label>
            <select name="sort_by">
                <option value="">Mới nhất</option>
                <option value="name_asc" @selected(request('sort_by') === 'name_asc')>Tên A-Z</option>
                <option value="name_desc" @selected(request('sort_by') === 'name_desc')>Tên Z-A</option>
                <option value="price_asc" @selected(request('sort_by') === 'price_asc')>Giá tăng dần</option>
                <option value="price_desc" @selected(request('sort_by') === 'price_desc')>Giá giảm dần</option>
            </select>
            
            <button class="filter-submit" type="submit">Áp dụng bộ lọc</button>
            
            @if(request()->hasAny(['keyword','gender','size','min_price','max_price','sort_by','category_id']))
                <a class="clear-filter" href="{{ route('shop.home') }}">Xóa tất cả bộ lọc</a>
            @endif
        </div>
    </form>
    
    <div class="filter-group">
        <h3>Danh mục</h3>
        <ul class="category-list">
            <li><a class="{{ !request('category_id') ? 'active' : '' }}" href="{{ route('shop.home', request()->except('category_id','page')) }}">Tất cả sản phẩm</a></li>
            @foreach($categories as $category)
                <li><a class="{{ (string)request('category_id') === (string)$category->id ? 'active' : '' }}" href="{{ route('shop.home', array_merge(request()->except('page'), ['category_id' => $category->id])) }}">{{ $category->name }}</a></li>
            @endforeach
        </ul>
    </div>
    
    <div class="account-box">
        <h3>Tài khoản</h3>
        @auth
            <a href="{{ route('profile.show') }}">Thông tin của tôi</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="filter-submit" type="submit" style="background: #dc2626;">Đăng xuất</button>
            </form>
        @else
            <a href="{{ route('login') }}">Đăng nhập</a>
            <a href="{{ route('register') }}">Đăng ký ngay</a>
        @endauth
    </div>
</aside>

<section class="shop-main">
@else
<section class="shop-main">
@endif

    <h2>Khám phá sản phẩm</h2>
    <div class="products">
        @forelse($products as $product)
            @php($variant = $product->variants->first())
            @php($image = $product->image ? asset('storage/'.$product->image) : ($variant?->image ? asset('storage/'.$variant->image) : 'https://via.placeholder.com/500x600?text=No+Image'))
            <a class="product-card" href="{{ auth()->check() && auth()->user()->isAdmin() ? route('products.show', $product) : route('shop.products.show', $product) }}">
                <img src="{{ $image }}" alt="{{ $product->name }}" onerror="this.onerror=null;this.src='https://via.placeholder.com/500x600?text=No+Image';">
                <div class="product-card-body">
                    <h3>{{ $product->name }}</h3>
                    <small>{{ ['male' => 'Đồ nam', 'female' => 'Đồ nữ', 'unisex' => 'Nam và nữ'][$product->gender] ?? 'Nam và nữ' }}</small>
                    <div class="price">{{ number_format($variant?->price ?: $product->base_price) }} đ</div>
                </div>
            </a>
        @empty
            <p style="color: #64748b; font-style: italic;">Chưa có sản phẩm nào phù hợp với tìm kiếm của bạn.</p>
        @endforelse
    </div>
    <div class="pagination">{{ $products->links() }}</div>

</section>
@if(!$isAdmin)</div>@endif
</div>
@endsection