@extends('layouts.app')

@section('content')
<style>
    /* Tone màu chủ đạo: Xanh Navy (#1e293b) và Vàng Gold (#b4860b) */
    .shop-home { color: #334155; font-family: system-ui, -apple-system, sans-serif; }
    
    /* Hero Banner & Thanh tìm kiếm trung tâm */
    .hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 40px 20px; text-align: center; border-radius: 12px; margin-bottom: 30px; }
    .hero h1 { margin: 0 0 12px; font-size: 28px; font-weight: 700; color: #f8fafc; }
    .hero p { font-size: 15px; color: #94a3b8; max-width: 600px; margin: 0 auto 20px; }
    
    .hero-search { display: flex; justify-content: center; max-width: 600px; margin: 0 auto; border: 1px solid #cbd5e1; border-radius: 30px;}
    .hero-search input { flex: 1; padding: 12px 20px; border: none; border-radius: 30px 0 0 30px; font-size: 14px; outline: none; }
    .hero-search button { padding: 12px 24px; background: #b4860b; color: #fff; border: none; border-radius: 0 30px 30px 0; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; }
    .hero-search button:hover { background: #9c6f19; }

    /* Layout & Sidebar Bộ lọc */
    .shop-layout { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 24px; }
    .shop-filter-sidebar { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; align-self: start; position: sticky; top: 20px; }
    .shop-filter-sidebar h3 { margin: 0 0 16px; font-size: 16px; color: #1e293b; border-left: 4px solid #b4860b; padding-left: 10px; }
    
    .filter-group { border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; }
    .filter-group label { display: block; font-size: 13px; font-weight: 700; margin: 10px 0 5px; color: #475569; }
    .filter-group input, .filter-group select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; }
    
    .filter-submit { width: 100%; background: #1e293b; color: #fff; border: 0; border-radius: 4px; padding: 10px; font-weight: 700; cursor: pointer; margin-top: 10px; }
    .clear-filter { display: block; color: #dc2626; text-align: center; margin-top: 12px; font-size: 13px; text-decoration: none; }
    
    .category-list { list-style: none; padding: 0; margin: 0; }
    .category-list li { margin: 8px 0; }
    .category-list a { color: #475569; text-decoration: none; font-size: 14px; }
    .category-list a.active { color: #b4860b; font-weight: 700; }

    /* Lưới sản phẩm */
    .shop-main h2 { color: #1e293b; font-size: 22px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0; margin-bottom: 20px; }
    .products { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
    .product-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; text-decoration: none; color: #1e293b; transition: 0.3s; }
    .product-card:hover { border-color: #cbd5e1; }
    .product-card img { width: 100%; height: 240px; object-fit: cover; }
    .product-card-body { padding: 15px; }
    .product-card h3 { margin: 0 0 6px; font-size: 15px; font-weight: 600; }
    .product-card small { color: #64748b; font-size: 12px; }
    .price { font-weight: 700; color: #b4860b; font-size: 16px; margin-top: 8px; }
    
    .pagination { margin-top: 30px; }
</style>

<div class="shop-home">
    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())

    <div class="hero">
        <p>Sự kết hợp hoàn hảo giữa chất liệu cao cấp và kĩ thuật may đo tỉ mỉ.</p>
    </div>

    @if(!$isAdmin)
    <div class="shop-layout">
        <aside class="shop-filter-sidebar">
            <form method="GET" action="{{ route('shop.home') }}">
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

            </form>
            
            <div class="filter-group" style="border-bottom: none;">
                <h3>Danh mục</h3>
                <ul class="category-list">
                    <li><a class="{{ !request('category_id') ? 'active' : '' }}" href="{{ route('shop.home', request()->except('category_id','page')) }}">Tất cả sản phẩm</a></li>
                    @foreach($categories as $category)
                        <li><a class="{{ (string)request('category_id') === (string)$category->id ? 'active' : '' }}" href="{{ route('shop.home', array_merge(request()->except('page'), ['category_id' => $category->id])) }}">{{ $category->name }}</a></li>
                    @endforeach
                </ul>
            </div>
        </aside>

        <section class="shop-main" id="products">
    @else
        <section class="shop-main" id="products" style="width: 100%;">
    @endif

            <h2>Khám phá sản phẩm</h2>
            <div class="products">
                @forelse($products as $product)
                    @php($variant = $product->variants->first())
                    @php($image = $product->image ? asset('storage/'.$product->image) : 'https://via.placeholder.com/500x600?text=No+Image')
                    <a class="product-card" href="{{ auth()->check() && auth()->user()->isAdmin() ? route('products.show', $product) : route('shop.products.show', ['product' => $product, 'return_to' => request()->fullUrl().'#products']) }}">
                        <img src="{{ $image }}" alt="{{ $product->name }}">
                        <div class="product-card-body">
                            <h3>{{ $product->name }}</h3>
                            <small>{{ ['male' => 'Đồ nam', 'female' => 'Đồ nữ', 'unisex' => 'Nam và nữ'][$product->gender] ?? 'Nam và nữ' }}</small>
                            <div class="price">{{ number_format($variant?->price ?: $product->base_price) }} đ</div>
                        </div>
                    </a>
                @empty
                    <p style="color: #64748b; font-style: italic;">Chưa có sản phẩm nào phù hợp.</p>
                @endforelse
            </div>
            <div class="pagination">{{ $products->links() }}</div>

        </section>
    @if(!$isAdmin)
    </div>
    @endif
</div>
@endsection
