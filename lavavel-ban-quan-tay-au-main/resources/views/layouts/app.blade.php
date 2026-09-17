<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bán quần Tây Âu - Hệ thống</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; display: flex; height: 100vh; overflow: hidden; }
        
        /* ================= PHẦN DÀNH CHO ADMIN ================= */
        .admin-body { background-color: #2c2c2c; }
        .admin-sidebar { width: 220px; background-color: #535353; display: flex; flex-direction: column; color: white; flex-shrink: 0; }
        .admin-sidebar .sidebar-logo { height: 70px; background-color: #d1e189; color: #333; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; border-bottom: 1px solid #444; }
        .admin-sidebar .sidebar-menu { list-style: none; display: flex; flex-direction: column; flex: 1; }
        .admin-sidebar .sidebar-menu li a { display: block; padding: 16px 20px; color: #d1d1d1; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .admin-sidebar .sidebar-menu li a:hover, .admin-sidebar .sidebar-menu li a.active { background-color: #3f3f3f; color: #ffffff; font-weight: bold; border-left: 4px solid #d1e189; }
        .admin-sidebar .sidebar-account { margin-top: auto; border-top: 1px solid #666; }

        .admin-main { min-width: 0; min-height: 0; flex: 1; display: flex; flex-direction: column; background-color: #f5f5f5; }
        .admin-header { height: 70px; background-color: #1f2937; border-bottom: none; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; }
        .admin-header .title { min-width: 0; flex: 1; font-size: 20px; font-weight: bold; color: #ffffff; }
        .admin-header .actions { flex-shrink: 0; display: flex; align-items: center; gap: 26px; margin-left: auto; }
        .admin-header .actions a { display: flex; align-items: center; gap: 8px; white-space: nowrap; font-size: 15px; font-weight: 500; color: #60a5fa; text-decoration: none; }
        .admin-header .actions a img { width: 30px; height: 30px; border: 1px solid #fff; border-radius: 50%; object-fit: cover; }
        .admin-header .actions a svg { width: 22px; height: 22px; flex-shrink: 0; fill: #8b5cf6; }
        .admin-header .actions form { flex-shrink: 0; }
        .btn-admin-logout { background-color: #ef4444; color: white; padding: 8px 16px; border: none; cursor: pointer; font-weight: bold; font-size: 14px; border-radius: 4px; transition: 0.2s; }
        .btn-admin-logout:hover { background-color: #dc2626; }
        
        .admin-content-area { min-height: 0; flex: 1; padding: 8px; overflow-y: auto; }
        .admin-card { background-color: #ffffff; padding: 12px; border-radius: 4px; min-height: 100%; border-top: 4px solid #333; }

        /* ================= PHẦN DÀNH CHO USER ================= */
        .user-shell { display: flex; width: 100%; min-height: 100vh; background: #f1f5f9; }
        .user-sidebar { position: relative; z-index: 20; width: 220px; background-color: #1f2937; color: white; display: flex; flex-direction: column; flex-shrink: 0; transition: width .25s ease; box-shadow: 10px 0 30px rgba(15, 23, 42, .12); }
        .user-shell--collapsed .user-sidebar { width: 78px; }
        .user-sidebar .sidebar-logo { height: 70px; background: linear-gradient(135deg, #d1e189 0%, #c4d66d 100%); color: #1f2937; display: flex; align-items: center; gap: 10px; padding: 0 14px; font-weight: bold; font-size: 15px; border-bottom: 1px solid rgba(31, 41, 55, 0.1); }
        .user-sidebar .sidebar-collapse-btn { width: 36px; height: 36px; border: none; border-radius: 8px; background: rgba(31, 41, 55, 0.12); color: #1f2937; font-size: 20px; line-height: 1; cursor: pointer; }
        .user-sidebar .sidebar-logo-text { white-space: nowrap; overflow: hidden; transition: opacity .2s ease, width .2s ease; }
        .user-shell--collapsed .sidebar-logo-text { width: 0; opacity: 0; }
        .user-sidebar .sidebar-menu { list-style: none; display: flex; flex-direction: column; flex: 1; padding: 12px 0; }
        .user-sidebar .sidebar-menu li { width: 100%; }
        .user-sidebar .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: #dbe2eb; text-decoration: none; font-size: 14px; transition: 0.2s, padding .25s ease; }
        .user-sidebar .sidebar-menu li a:hover, .user-sidebar .sidebar-menu li a.active { background-color: rgba(255,255,255,.06); color: #ffffff; font-weight: bold; border-left: 4px solid #d1e189; padding-left: 12px; }
        .user-sidebar .sidebar-menu .menu-icon { display: inline-flex; align-items: center; justify-content: center; width: 18px; min-width: 18px; font-size: 16px; }
        .user-sidebar .sidebar-menu .nav-label { white-space: nowrap; overflow: hidden; transition: opacity .2s ease, width .2s ease; }
        .user-shell--collapsed .user-sidebar .sidebar-menu li a { justify-content: center; padding-left: 10px; padding-right: 10px; }
        .user-shell--collapsed .user-sidebar .sidebar-menu li a:hover, .user-shell--collapsed .user-sidebar .sidebar-menu li a.active { padding-left: 10px; }
        .user-shell--collapsed .user-sidebar .sidebar-menu .nav-label { width: 0; opacity: 0; }
        .user-sidebar .sidebar-account { margin-top: auto; border-top: 1px solid rgba(255,255,255,.12); }

        .user-menu-backdrop { position: fixed; inset: 0; z-index: 1000; border: 0; background: rgba(15, 23, 42, .55); opacity: 0; visibility: hidden; cursor: pointer; transition: opacity .25s ease, visibility .25s ease; }
        .user-menu-backdrop.is-open { opacity: 1; visibility: visible; }
        .user-main { width: 100%; min-width: 0; flex: 1; display: flex; flex-direction: column; background-color: #f1f5f9; }
        .user-header { height: 70px; background-color: #1f2937; display: flex; justify-content: space-between; align-items: center; padding: 0 20px 0 14px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); }
        .user-menu-toggle { width: 42px; height: 42px; margin-right: 12px; border: 1px solid #475569; border-radius: 7px; background: #334155; color: #fff; font-size: 25px; line-height: 1; cursor: pointer; flex-shrink: 0; transition: background .2s, border-color .2s; }
        .user-menu-toggle:hover, .user-menu-toggle:focus-visible { background: #475569; border-color: #64748b; outline: none; }
        .user-header .logo { font-size: 22px; font-weight: bold; color: #ffffff; text-transform: uppercase; text-decoration: none; flex-shrink: 0; }
        .user-header .actions { flex-shrink: 0; display: flex; align-items: center; gap: 18px; margin-left: auto; }
        .user-header .actions form { flex-shrink: 0; }
        .header-cart-link { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; color: #fff; text-decoration: none; transition: background .2s, color .2s; }
        .header-cart-link:hover, .header-cart-link:focus-visible { background: #334155; color: #d1e189; outline: none; }
        .header-cart-link svg { width: 25px; height: 25px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .header-user-profile { display: flex; align-items: center; gap: 8px; white-space: nowrap; color: #60a5fa; text-decoration: none; font-weight: 500; font-size: 15px; }
        .header-user-profile img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #fff; }
        .header-user-profile svg { width: 20px; height: 20px; fill: #8b5cf6; }
        .search-wrapper { position: relative; width: min(600px, 45vw); margin-left: 20px; }
        .hero-search { display: flex; justify-content: center; width: 100%; margin: 0 auto; border-radius: 9999px; background: #fff; overflow: hidden; border: 1px solid #cbd5e1; }
        .hero-search input { flex: 1; padding: 12px 24px; border: none; background: transparent; font-size: 15px; outline: none; }
        .hero-search button { padding: 12px 30px; background: #b4860b; color: #fff; border: none; border-radius: 0 9999px 9999px 0; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .hero-search button:hover { background: #9c6f19; }
        .search-suggestions { display: none; position: absolute; top: calc(100% + 8px); left: 0; right: 0; z-index: 200; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,0.18); overflow: hidden; }
        .search-suggestions.active { display: block; }
        .search-suggestion { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #1e293b; text-decoration: none; font-size: 14px; }
        .search-suggestion:hover, .search-suggestion:focus { background: #f8fafc; }
        .search-suggestion img { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; flex-shrink: 0; }
        .btn-user-logout { background-color: #ef4444; color: white; padding: 8px 16px; border: none; border-radius: 4px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn-user-logout:hover { background-color: #dc2626; }
        .user-content-area { flex: 1; padding: 28px; overflow-y: auto; }
        .user-card { background-color: #ffffff; padding: 30px; border-radius: 14px; min-height: calc(100vh - 70px - 56px); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06); }

        @media (max-width: 900px) {
            .user-shell { position: relative; }
            .user-sidebar { position: fixed; inset: 0 auto 0 0; width: min(290px, 86vw); transform: translateX(-100%); visibility: hidden; box-shadow: 10px 0 30px rgba(15, 23, 42, .24); }
            .user-sidebar.is-open { transform: translateX(0); visibility: visible; }
            .user-shell--collapsed .user-sidebar { width: min(290px, 86vw); }
            .user-main { width: 100%; }
            .user-header { padding: 0 16px; }
            .user-header .logo { font-size: 17px; }
            .search-wrapper { width: auto; min-width: 0; flex: 1; margin-left: 12px; }
            .user-header .actions { gap: 10px; margin-left: 12px; }
            .header-user-profile span { display: none; }
            .user-content-area { padding: 18px; }
            .user-card { padding: 20px; min-height: auto; }
        }
        @media (max-width: 640px) {
            .user-header .logo, .search-wrapper { display: none; }
            .user-header .actions { margin-left: auto; }
            .btn-user-logout { padding: 8px 10px; }
        }
    </style>
</head>
<body>

    @if(auth()->check() && auth()->user()->role === 'admin')
        {{-- ================= GIAO DIỆN ADMIN ================= --}}
        <div class="admin-sidebar">
            <div class="sidebar-logo">ADMIN QUẦN TÂY</div>
            <ul class="sidebar-menu">
                <li><a href="{{ route('shop.home') }}">Trang chủ shop</a></li>
                <li><a href="{{ route('categories.index') }}" class="{{ Request::is('categories*') ? 'active' : '' }}">Danh mục</a></li>
                <li><a href="{{ route('products.index') }}" class="{{ Request::is('products*') ? 'active' : '' }}">Sản Phẩm</a></li>
                <li><a href="{{ route('admin.orders.index') }}" class="{{ Request::is('admin/orders*') ? 'active' : '' }}">Đơn hàng</a></li>
                <li><a href="{{ route('admin.reports.index') }}" class="{{ Request::is('admin/reports*') ? 'active' : '' }}">Thống kê báo cáo</a></li>
                <li><a href="{{ route('chat.admin.index') }}" class="{{ Request::is('admin/nhan-tin*') ? 'active' : '' }}">Nhắn tin</a></li>
                <li class="sidebar-account">
                    <a href="{{ route('admin.profile.show') }}" class="{{ Request::is('admin/profile*') ? 'active' : '' }}">Tài khoản</a>
                </li>
            </ul>
        </div>
        
        <div class="admin-main">
            <div class="admin-header">
                <div class="title">HỆ THỐNG QUẢN LÝ QUẦN TÂY ÂU</div>
                <div class="actions">
                    <a href="{{ route('chat.admin.index') }}" title="Tin nhắn" style="position: relative; justify-content: center; color: #d1d1d1; transition: color .2s;">
                        <svg viewBox="0 0 24 24" style="width: 25px; height: 25px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        @php($unreadAdmin = app(App\Services\Chat\ChatService::class)->unreadForAdmins())
                        @if($unreadAdmin > 0)
                            <span style="position: absolute; top: -5px; right: -8px; background: #ef4444; color: #fff; font-size: 10px; font-weight: bold; border-radius: 50%; min-width: 18px; min-height: 18px; display: flex; align-items: center; justify-content: center; line-height: 1;">{{ $unreadAdmin }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.profile.show') }}">
                        @if(auth()->user()->avatar)
                            <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="Ảnh đại diện của {{ auth()->user()->name }}">
                        @else
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        @endif
                        <span>Xin chào! {{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn-admin-logout">Đăng xuất</button>
                    </form>
                </div>
            </div>
            
            <div class="admin-content-area">
                <div class="admin-card">
                    @yield('content')
                </div>
            </div>
        </div>

        <span
            data-admin-presence-heartbeat
            data-presence-url="{{ route('chat.admin.presence') }}"
            hidden
            aria-hidden="true"
        ></span>
        <script>
            (function () {
                const marker = document.querySelector('[data-admin-presence-heartbeat]');
                if (!marker) return;

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                async function sendAdminPresence() {
                    try {
                        await fetch(marker.dataset.presenceUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });
                    } catch (error) {
                        // Presence is best-effort; the next heartbeat can recover it.
                    }
                }

                sendAdminPresence();
                window.setInterval(sendAdminPresence, 30000);
            }());
        </script>

    @else
        {{-- ================= GIAO DIỆN USER ================= --}}
        <div class="user-shell" id="user-shell">
            <nav class="user-sidebar" id="user-navigation" data-user-menu aria-label="Điều hướng khách hàng" aria-hidden="false">
                <div class="sidebar-logo">
                    <span class="sidebar-logo-text">TRANG KHÁCH HÀNG</span>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="{{ route('shop.home') }}"><span class="menu-icon">🏡</span><span class="nav-label">Trang chủ</span></a></li>
                    <li><a href="{{ url('gio-hang') }}" class="{{ Request::is('gio-hang*') ? 'active' : '' }}"><span class="menu-icon">📦</span><span class="nav-label">Giỏ hàng</span></a></li>
                    <li><a href="{{ route('user.addresses.index') }}" class="{{ Request::is('user/addresses*') ? 'active' : '' }}"><span class="menu-icon">📍</span><span class="nav-label">Địa chỉ</span></a></li>
                    <li><a href="{{ route('user.orders.index', ['status' => 'completed']) }}" class="{{ Request::is('user/don-mua*') ? 'active' : '' }}"><span class="menu-icon">🛍️</span><span class="nav-label">Đơn mua</span></a></li>
                    <li><a href="{{ route('chat.user.index') }}" class="{{ Request::is('nhan-tin*') ? 'active' : '' }}"><span class="menu-icon">💬</span><span class="nav-label">Nhắn tin</span></a></li>
                    <li class="sidebar-account">
                        <a href="{{ route('user.profile.show') }}" class="{{ Request::is('user/profile*') ? 'active' : '' }}"><span class="menu-icon">👤</span><span class="nav-label">Hồ sơ</span></a>
                    </li>
                </ul>
            </nav>
            <button type="button" class="user-menu-backdrop" data-user-menu-backdrop aria-label="Đóng menu" tabindex="-1"></button>

            <div class="user-main">
                <div class="user-header">
                    <button type="button" class="user-menu-toggle" data-user-menu-toggle aria-controls="user-navigation" aria-expanded="false" aria-label="Mở menu">☰</button>
                    <a href="{{ route('shop.home') }}" class="logo">QUẦN TÂY ÂU</a>

                    <div class="search-wrapper">
                        <form class="hero-search" method="GET" action="{{ route('shop.home') }}" autocomplete="off">
                            @if(request('gender')) <input type="hidden" name="gender" value="{{ request('gender') }}"> @endif
                            @if(request('size')) <input type="hidden" name="size" value="{{ request('size') }}"> @endif
                            @if(request('category_id')) <input type="hidden" name="category_id" value="{{ request('category_id') }}"> @endif
                            <input id="product-search-input" type="text" name="keyword" placeholder="Nhập tên mẫu quần âu bạn tìm..." value="{{ request('keyword') }}" aria-label="Tìm kiếm sản phẩm" aria-controls="product-search-suggestions" aria-expanded="false">
                            <button type="submit">Tìm kiếm</button>
                        </form>
                        <div id="product-search-suggestions" class="search-suggestions" role="listbox"></div>
                    </div>

                    <div class="actions">
                        @if(auth()->check())
                            <a href="{{ route('cart.index') }}" class="header-cart-link" data-user-cart-link aria-label="Giỏ hàng" title="Giỏ hàng">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 7H6"></path>
                                    <circle cx="10" cy="20" r="1"></circle>
                                    <circle cx="18" cy="20" r="1"></circle>
                                </svg>
                            </a>
                            <a href="{{ route('chat.user.index') }}" class="header-cart-link" aria-label="Tin nhắn" title="Tin nhắn" style="position: relative;">
                                <svg viewBox="0 0 24 24" style="width: 23px; height: 23px;" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                                @php($unreadUser = app(App\Services\Chat\ChatService::class)->unreadForUser(auth()->user()))
                                @if($unreadUser > 0)
                                    <span style="position: absolute; top: -1px; right: -3px; background: #ef4444; color: #fff; font-size: 10px; font-weight: bold; border-radius: 50%; min-width: 17px; min-height: 17px; display: flex; align-items: center; justify-content: center; line-height: 1;">{{ $unreadUser }}</span>
                                @endif
                            </a>
                            <a href="{{ route('user.profile.show') }}" class="header-user-profile">
                                @if(auth()->user()->avatar)
                                    <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="Avatar">
                                @else
                                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                @endif
                                <span>Xin chào! {{ auth()->user()->name }}</span>
                            </a>
                        @endif

                        <form method="POST" action="{{ route('buyer.logout') }}" style="display: inline; margin: 0;">
                            @csrf
                            <button type="submit" class="btn-user-logout">Đăng xuất</button>
                        </form>
                    </div>
                </div>

                <div class="user-content-area">
                    <div class="user-card">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(request()->routeIs('checkout'))
    <script>
        // Trang thanh toán cũ gọi trực tiếp Photon và có thể bị CORS chặn.
        // Chuyển riêng yêu cầu đó qua endpoint Laravel có cache và nguồn dự phòng.
        (function () {
            const nativeFetch = window.fetch.bind(window);
            const searchUrl = @json(route('user.addresses.search'));
            window.fetch = async function (resource, options) {
                const requestedUrl = typeof resource === 'string' ? resource : resource.url;
                let parsed;
                try { parsed = new URL(requestedUrl, window.location.href); } catch { return nativeFetch(resource, options); }
                if (parsed.hostname !== 'photon.komoot.io') return nativeFetch(resource, options);

                const ward = document.querySelector('#ward');
                const province = document.querySelector('#province');
                const params = new URLSearchParams({
                    q: parsed.searchParams.get('q') || '',
                    ward: ward?.selectedOptions[0]?.text || '',
                    province: province?.selectedOptions[0]?.text || ''
                });
                const response = await nativeFetch(`${searchUrl}?${params}`, {
                    ...options,
                    headers: { ...(options?.headers || {}), Accept: 'application/json' }
                });
                if (!response.ok) return response;
                const payload = await response.json();
                const features = (payload.results || []).map(result => ({
                    geometry: { coordinates: [result.longitude, result.latitude] },
                    properties: {
                        name: result.street,
                        district: ward?.selectedOptions[0]?.text || '',
                        state: province?.selectedOptions[0]?.text || ''
                    }
                }));
                return new Response(JSON.stringify({ features }), {
                    status: response.status,
                    headers: { 'Content-Type': 'application/json' }
                });
            };
        }());
    </script>
    @endif

    <script>
        (function () {
            const shell = document.getElementById('user-shell');
            const toggle = document.querySelector('[data-user-menu-toggle]');
            const menu = document.querySelector('[data-user-menu]');
            const sidebarToggle = document.querySelector('[data-user-sidebar-collapse]');
            const backdrop = document.querySelector('[data-user-menu-backdrop]');
            if (!toggle || !menu || !backdrop || !shell) return;

            function isMobile() {
                return window.innerWidth <= 900;
            }

            function setMenu(open) {
                if (isMobile()) {
                    menu.classList.toggle('is-open', open);
                    backdrop.classList.toggle('is-open', open);
                    menu.setAttribute('aria-hidden', String(!open));
                    toggle.setAttribute('aria-expanded', String(open));
                    toggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
                    backdrop.tabIndex = open ? 0 : -1;
                    return;
                }

                shell.classList.toggle('user-shell--collapsed', !open);
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Thu gọn menu' : 'Mở menu');
                menu.setAttribute('aria-hidden', 'false');
                backdrop.classList.remove('is-open');
                backdrop.tabIndex = -1;
            }

            toggle.addEventListener('click', function () {
                if (isMobile()) {
                    setMenu(toggle.getAttribute('aria-expanded') !== 'true');
                    return;
                }

                const collapsed = shell.classList.contains('user-shell--collapsed');
                shell.classList.toggle('user-shell--collapsed', !collapsed);
                toggle.setAttribute('aria-expanded', String(!collapsed));
                toggle.setAttribute('aria-label', collapsed ? 'Thu gọn menu' : 'Mở menu');
            });

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    if (isMobile()) {
                        setMenu(false);
                        return;
                    }
                    const collapsed = shell.classList.contains('user-shell--collapsed');
                    shell.classList.toggle('user-shell--collapsed', !collapsed);
                    toggle.setAttribute('aria-expanded', String(!collapsed));
                    toggle.setAttribute('aria-label', collapsed ? 'Thu gọn menu' : 'Mở menu');
                });
            }

            backdrop.addEventListener('click', function () { setMenu(false); });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isMobile() && toggle.getAttribute('aria-expanded') === 'true') {
                    setMenu(false);
                    toggle.focus();
                }
            });
            window.addEventListener('resize', function () {
                if (isMobile()) {
                    menu.classList.remove('is-open');
                    backdrop.classList.remove('is-open');
                    menu.setAttribute('aria-hidden', 'true');
                    toggle.setAttribute('aria-expanded', 'false');
                    backdrop.tabIndex = -1;
                    return;
                }
                shell.classList.remove('user-shell--collapsed');
                menu.setAttribute('aria-hidden', 'false');
                toggle.setAttribute('aria-expanded', 'true');
            });
        }());

        (function () {
            const input = document.getElementById('product-search-input');
            const suggestions = document.getElementById('product-search-suggestions');
            if (!input || !suggestions) return;

            let requestController;
            let requestNumber = 0;

            function hideSuggestions() {
                suggestions.innerHTML = '';
                suggestions.classList.remove('active');
                input.setAttribute('aria-expanded', 'false');
            }

            function renderSuggestions(products) {
                if (!products.length) {
                    hideSuggestions();
                    return;
                }

                suggestions.innerHTML = products.map(function (product) {
                    const image = product.image
                        ? '<img src="' + product.image + '" alt="">'
                        : '';
                    return '<a class="search-suggestion" role="option" href="' + product.url + '">' + image + '<span>' + product.name + '</span></a>';
                }).join('');
                suggestions.classList.add('active');
                input.setAttribute('aria-expanded', 'true');
            }

            input.addEventListener('input', function () {
                const keyword = input.value.trim();
                const currentRequest = ++requestNumber;
                if (requestController) requestController.abort();
                if (!keyword) {
                    hideSuggestions();
                    return;
                }

                requestController = new AbortController();
                fetch('{{ route('shop.products.suggestions') }}?keyword=' + encodeURIComponent(keyword), { signal: requestController.signal })
                    .then(function (response) { return response.ok ? response.json() : []; })
                    .then(function (products) {
                        if (currentRequest === requestNumber) renderSuggestions(products);
                    })
                    .catch(function (error) {
                        if (error.name !== 'AbortError') hideSuggestions();
                    });
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.search-wrapper')) hideSuggestions();
            });
        }());
    </script>
</body>
</html>
