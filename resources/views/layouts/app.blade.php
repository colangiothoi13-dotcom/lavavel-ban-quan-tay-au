<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bán quần Tây Âu</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background-color: #2c2c2c; display: flex; height: 100vh; overflow: hidden; }
        
        /* SIDEBAR */
        .sidebar { width: 220px; background-color: #535353; display: flex; flex-direction: column; color: white; flex-shrink: 0; }
        .sidebar-logo { height: 70px; background-color: #d1e189; border-bottom: 1px solid #444; }
        .sidebar-menu { list-style: none; display: flex; flex-direction: column; flex: 1; }
        .sidebar-menu li a { display: block; padding: 16px 20px; color: #d1d1d1; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background-color: #3f3f3f; color: #ffffff; font-weight: bold; }
        .sidebar-account { margin-top: auto; border-top: 1px solid #666; }

        /* MAIN CONTENT AREA */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; background-color: #f5f5f5; }
        .header-bar { height: 70px; background-color: #ffffff; border-bottom: 2px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; }
        .header-title { font-size: 22px; font-weight: bold; color: #222222; }
        .header-actions { display: flex; align-items: center; gap: 14px; }
        .header-actions .user-name { font-size: 14px; font-weight: bold; color: #333; text-decoration: none; }
        .btn-logout { background-color: #111111; color: white; padding: 8px 18px; border: none; cursor: pointer; font-weight: bold; font-size: 13px; border-radius: 2px; }
        .btn-logout:hover { background-color: #dc2626; }
        
        /* KHUNG TRẮNG NỘI DUNG */
        .content-container { flex: 1; padding: 25px; overflow-y: auto; }
        .card-box { background-color: #ffffff; padding: 25px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); min-height: 100%; }
    </style>
</head>
<body>

    {{-- CHỈ ADMIN MỚI ĐƯỢC HIỂN THỊ SIDEBAR BÊN TRÁI --}}
    @if(auth()->check() && auth()->user()->role === 'admin')
    <div class="sidebar">
        <div class="sidebar-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="{{ route('shop.home') }}">Trang chủ shop</a></li>
            <li><a href="{{ route('categories.index') }}" class="{{ Request::is('categories*') ? 'active' : '' }}">Danh mục</a></li>
            <li><a href="{{ route('products.index') }}" class="{{ Request::is('products*') ? 'active' : '' }}">Sản Phẩm</a></li>
            <li class="sidebar-account">
                <a href="{{ route('profile.show') }}" class="{{ Request::is('admin/profile*') || Request::is('profile*') ? 'active' : '' }}">Tài khoản</a>
            </li>
        </ul>
    </div>
    @endif

    <div class="main-wrapper">
        
        {{-- CHỈ ADMIN MỚI ĐƯỢC HIỂN THỊ HEADER BÊN TRÊN --}}
        @if(auth()->check() && auth()->user()->role === 'admin')
        <div class="header-bar">
            <div class="header-title">HỆ THỐNG QUẢN LÝ QUẦN TÂY ÂU</div>
            <div class="header-actions">
                <a href="{{ route('profile.show') }}" class="user-name">👤 {{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-logout">Đăng xuất</button>
                </form>
            </div>
        </div>
        @endif

        <div class="content-container">
            
            {{-- NẾU LÀ USER THƯỜNG, HIỆN NÚT QUAY LẠI TRANG MUA SẮM --}}
            @if(auth()->check() && auth()->user()->role !== 'admin')
            <div style="margin-bottom: 20px;">
                <a href="{{ route('shop.home') }}" style="text-decoration: none; color: #2563eb; font-weight: bold; font-size: 16px;">
                    &larr; Quay về trang mua sắm
                </a>
            </div>
            @endif

            <div class="card-box">
                @yield('content')
            </div>
        </div>
    </div>

</body>
</html>