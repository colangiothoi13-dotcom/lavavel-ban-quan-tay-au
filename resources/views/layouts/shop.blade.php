<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cửa hàng quần Tây Âu' }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#f5f6f8;color:#20252b;font-family:Arial,sans-serif}
        .nav{background:#202a35;color:#fff;padding:18px 5%;display:flex;align-items:center;justify-content:space-between}
        .brand{font-weight:700;font-size:20px;color:#fff;text-decoration:none}
        .nav-right{display:flex;align-items:center;}
        .nav a{color:#fff;text-decoration:none;margin-left:20px}
        
        /* CSS cho form & nút đăng xuất trên Navbar */
        .logout-form-nav { display: inline-block; margin-left: 15px; }
        .btn-logout-nav { background: #dc2626; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-logout-nav:hover { background: #b91c1c; }

        .container{max-width:1180px;margin:0 auto;padding:28px 20px}
        .notice{padding:12px 16px;background:#e3f6e9;color:#166534;margin-bottom:18px;border-radius:5px}
        .error{padding:12px 16px;background:#fee2e2;color:#991b1b;margin-bottom:18px;border-radius:5px}
        .hero{background:#dfe9e2;padding:38px 34px;margin-bottom:28px;border-radius:8px}
        .hero h1{margin:0 0 8px;font-size:34px}
        .products{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
        .product-card{background:#fff;border:1px solid #e3e6e8;border-radius:6px;overflow:hidden;transition:box-shadow .2s}
        .product-card:hover{border-color:#cbd5e1}
        .product-card img{width:100%;height:250px;object-fit:cover;background:#e9ecef}
        .product-card-body{padding:15px}
        .product-card h3{margin:0 0 10px;font-size:17px}
        .product-card a{text-decoration:none;color:#20252b}
        .price{font-weight:700;color:#b45309;font-size:18px}
        .btn{display:inline-block;border:0;border-radius:4px;background:#b45309;color:#fff;padding:11px 16px;text-decoration:none;cursor:pointer;font-weight:600}
        .detail{display:grid;grid-template-columns:minmax(280px,1fr) 1fr;gap:32px;background:#fff;padding:32px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
        .detail img{width:100%;max-height:520px;object-fit:contain;background:#f1f2f3}
        .field{margin:13px 0}
        .field label{display:block;font-weight:600;margin-bottom:6px}
        .field input,.field select,.field textarea{width:100%;padding:10px;border:1px solid #ccd1d5;border-radius:4px}
        .cart-row{display:grid;grid-template-columns:1fr 110px 130px 90px;align-items:center;gap:15px;background:#fff;border-bottom:1px solid #e5e7eb;padding:14px}
        .cart-total{text-align:right;font-size:20px;font-weight:700;margin:22px 0}
        .pagination{margin-top:24px}
        @media(max-width:700px){
            .detail{grid-template-columns:1fr;padding:20px}
            .nav{display:block}
            .nav div{margin-top:12px}
            .cart-row{grid-template-columns:1fr}
            .hero h1{font-size:26px}
        }
    </style>
</head>
<body>

<nav class="nav">
    <a class="brand" href="{{ route('shop.home') }}">QUẦN TÂY ÂU</a>
    <div class="nav-right">
        <a href="{{ route('shop.home') }}">Trang chủ</a>
        @auth
            <!-- Hiển thị tên người dùng và dẫn vào trang profile -->
            <a href="{{ route(auth()->user()->isAdmin() ? 'admin.profile.show' : 'user.profile.show') }}" style="color: #60a5fa; font-weight: bold;">
                👤 {{ auth()->user()->name }}
            </a>

            <!-- Nút Quản trị chỉ hiện nếu là Admin -->
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}">Quản trị</a>
            @endif

            <!-- Form nút đăng xuất nhanh -->
            <form method="POST" action="{{ route(auth()->user()->isAdmin() ? 'admin.logout' : 'buyer.logout') }}" class="logout-form-nav">
                @csrf
                <button type="submit" class="btn-logout-nav">Đăng xuất</button>
            </form>
        @else
            <a href="{{ route('login') }}">Đăng nhập</a>
        @endauth
    </div>
</nav>

<main class="container">
    @if(session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif 

    @yield('content')
</main>

</body>
</html>
