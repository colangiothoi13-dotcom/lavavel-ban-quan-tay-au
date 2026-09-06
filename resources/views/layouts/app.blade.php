<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .admin-main { min-width: 0; flex: 1; display: flex; flex-direction: column; background-color: #f5f5f5; }
        .admin-header { height: 70px; background-color: #1f2937; border-bottom: none; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; }
        .admin-header .title { min-width: 0; flex: 1; font-size: 20px; font-weight: bold; color: #ffffff; }
        .admin-header .actions { flex-shrink: 0; display: flex; align-items: center; gap: 26px; margin-left: auto; }
        .admin-header .actions a { display: flex; align-items: center; gap: 8px; white-space: nowrap; font-size: 15px; font-weight: 500; color: #60a5fa; text-decoration: none; }
        .admin-header .actions a img { width: 30px; height: 30px; border: 1px solid #fff; border-radius: 50%; object-fit: cover; }
        .admin-header .actions a svg { width: 22px; height: 22px; flex-shrink: 0; fill: #8b5cf6; }
        .admin-header .actions form { flex-shrink: 0; }
        .btn-admin-logout { background-color: #ef4444; color: white; padding: 8px 16px; border: none; cursor: pointer; font-weight: bold; font-size: 14px; border-radius: 4px; transition: 0.2s; }
        .btn-admin-logout:hover { background-color: #dc2626; }
        
        .admin-content-area { flex: 1; padding: 25px; overflow-y: auto; }
        .admin-card { background-color: #ffffff; padding: 25px; border-radius: 4px; min-height: 100%; border-top: 4px solid #333; }

        /* ================= PHẦN DÀNH CHO USER ================= */
        .user-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1001; width: min(300px, 86vw); background-color: #535353; color: white; display: flex; flex-direction: column; transform: translateX(-100%); visibility: hidden; box-shadow: 10px 0 30px rgba(15, 23, 42, .24); transition: transform .25s ease, visibility .25s ease; }
        .user-sidebar.is-open { transform: translateX(0); visibility: visible; }
        .user-sidebar-persistent { position: relative; inset: auto; width: 220px; transform: none; visibility: visible; box-shadow: none; flex-shrink: 0; }
        .user-sidebar .sidebar-logo { height: 70px; background-color: #d1e189; color: #333; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; border-bottom: 1px solid #444; }
        .user-sidebar .sidebar-menu { list-style: none; display: flex; flex-direction: column; flex: 1; }
        .user-sidebar .sidebar-menu li a { display: block; padding: 16px 20px; color: #d1d1d1; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .user-sidebar .sidebar-menu li a:hover, .user-sidebar .sidebar-menu li a.active { background-color: #3f3f3f; color: #ffffff; font-weight: bold; border-left: 4px solid #d1e189; }
        .user-sidebar .sidebar-account { margin-top: auto; border-top: 1px solid #666; }

        .user-menu-backdrop { position: fixed; inset: 0; z-index: 1000; border: 0; background: rgba(15, 23, 42, .55); opacity: 0; visibility: hidden; cursor: pointer; transition: opacity .25s ease, visibility .25s ease; }
        .user-menu-backdrop.is-open { opacity: 1; visibility: visible; }
        .user-main { width: 100%; min-width: 0; flex: 1; display: flex; flex-direction: column; background-color: #f1f5f9; }
        .user-header { height: 70px; background-color: #1f2937; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; }
        .user-menu-toggle { width: 42px; height: 42px; margin-right: 16px; border: 1px solid #475569; border-radius: 7px; background: #334155; color: #fff; font-size: 25px; line-height: 1; cursor: pointer; flex-shrink: 0; transition: background .2s, border-color .2s; }
        .user-menu-toggle:hover, .user-menu-toggle:focus-visible { background: #475569; border-color: #64748b; outline: none; }
        .user-header .logo { font-size: 22px; font-weight: bold; color: #ffffff; text-transform: uppercase; text-decoration: none; flex-shrink: 0; }
        .user-header .actions { flex-shrink: 0; display: flex; align-items: center; gap: 26px; margin-left: auto; }
        .user-header .actions form { flex-shrink: 0; }
        .header-cart-link { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; color: #fff; text-decoration: none; transition: background .2s, color .2s; }
        .header-cart-link:hover, .header-cart-link:focus-visible { background: #334155; color: #d1e189; outline: none; }
        .header-cart-link svg { width: 25px; height: 25px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        
        /* CSS cho phần Xin chào, Avatar và Tên */
        .header-user-profile { display: flex; align-items: center; gap: 8px; white-space: nowrap; color: #60a5fa; text-decoration: none; font-weight: 500; font-size: 15px; }
        .header-user-profile img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #fff; }
        .header-user-profile svg { width: 20px; height: 20px; fill: #8b5cf6; }

        /* CSS cho thanh tìm kiếm (.hero-search) */
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 40px 20px; text-align: center; border-radius: 12px; margin-bottom: 30px; }
        .hero h1 { margin: 0 0 12px; font-size: 28px; font-weight: 700; color: #f8fafc; }
        .hero p { font-size: 15px; color: #94a3b8; max-width: 600px; margin: 0 auto 20px; }
        
        .search-wrapper { position: relative; width: min(600px, 45vw); margin-left: 24px; }
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

        .user-content-area { flex: 1; padding: 30px; overflow-y: auto; }
        .user-card { background-color: #ffffff; padding: 30px; border-radius: 12px; min-height: 100%; }
        .user-panel-layout .user-menu-toggle { display: none; }
        .user-panel-layout .user-content-area { padding: 25px; }
        .user-panel-layout .user-card { min-height: 100%; border-radius: 4px; border-top: 4px solid #333; }
        @media (max-width: 900px) {
            .user-sidebar-persistent { position: fixed; inset: 0 auto 0 0; width: min(300px, 86vw); transform: translateX(-100%); visibility: hidden; box-shadow: 10px 0 30px rgba(15, 23, 42, .24); }
            .user-sidebar-persistent.is-open { transform: translateX(0); visibility: visible; }
            .user-panel-layout .user-menu-toggle { display: block; }
            .user-header { padding: 0 16px; }
            .user-header .logo { font-size: 17px; }
            .search-wrapper { width: auto; min-width: 0; flex: 1; margin-left: 12px; }
            .user-header .actions { gap: 10px; margin-left: 12px; }
            .header-user-profile span { display: none; }
            .user-content-area { padding: 18px; }
            .user-card { padding: 20px; }
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
                <li class="sidebar-account">
                    <a href="{{ route('admin.profile.show') }}" class="{{ Request::is('admin/profile*') ? 'active' : '' }}">Tài khoản</a>
                </li>
            </ul>
        </div>
        
        <div class="admin-main">
            <div class="admin-header">
                <div class="title">HỆ THỐNG QUẢN LÝ QUẦN TÂY ÂU</div>
                <div class="actions">
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

    @else
        {{-- ================= GIAO DIỆN USER ================= --}}
        @php
            $userPanelLayout = Request::is('gio-hang*')
                || Request::is('user/*')
                || Request::is('thanh-toan*');
        @endphp
        <nav class="user-sidebar {{ $userPanelLayout ? 'user-sidebar-persistent' : '' }}" id="user-navigation" data-user-menu aria-label="Điều hướng khách hàng" aria-hidden="{{ $userPanelLayout ? 'false' : 'true' }}">
            <div class="sidebar-logo">TRANG KHÁCH HÀNG</div>
            <ul class="sidebar-menu">
                <li><a href="{{ route('shop.home') }}">🏡Trang chủ</a></li>                
                <li><a href="{{ url('gio-hang') }}" class="{{ Request::is('gio-hang*') ? 'active' : '' }}">📦Giỏ hàng</a></li>
                <li><a href="{{ route('user.addresses.index') }}" class="{{ Request::is('user/addresses*') ? 'active' : '' }}">📍 Địa chỉ</a></li>
                <li><a href="{{ route('user.orders.index', ['status' => 'completed']) }}" class="{{ Request::is('user/don-mua*') ? 'active' : '' }}">🛍️ Đơn mua</a></li>
                <li class="sidebar-account">
                    <a href="{{ route('user.profile.show') }}" class="{{ Request::is('user/profile*') ? 'active' : '' }}">👤 Hồ sơ tài khoản</a>
                </li>
            </ul>
        </nav>
        <button type="button" class="user-menu-backdrop" data-user-menu-backdrop aria-label="Đóng menu" tabindex="-1"></button>

        <div class="user-main {{ $userPanelLayout ? 'user-panel-layout' : '' }}">
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
                    {{-- THÊM PHẦN XIN CHÀO, AVATAR VÀ TÊN Ở ĐÂY --}}
                    @if(auth()->check())
                        <a href="{{ route('cart.index') }}" class="header-cart-link" data-user-cart-link aria-label="Giỏ hàng" title="Giỏ hàng">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 7H6"></path>
                                <circle cx="10" cy="20" r="1"></circle>
                                <circle cx="18" cy="20" r="1"></circle>
                            </svg>
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
            const toggle = document.querySelector('[data-user-menu-toggle]');
            const menu = document.querySelector('[data-user-menu]');
            const backdrop = document.querySelector('[data-user-menu-backdrop]');
            if (!toggle || !menu || !backdrop) return;

            function setMenu(open) {
                menu.classList.toggle('is-open', open);
                backdrop.classList.toggle('is-open', open);
                menu.setAttribute('aria-hidden', String(!open));
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
                backdrop.tabIndex = open ? 0 : -1;
            }

            toggle.addEventListener('click', function () {
                setMenu(toggle.getAttribute('aria-expanded') !== 'true');
            });
            backdrop.addEventListener('click', function () { setMenu(false); });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                    setMenu(false);
                    toggle.focus();
                }
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
