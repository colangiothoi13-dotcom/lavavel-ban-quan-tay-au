<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            <!-- Popup chat is the only chat entry point -->
        @else
            <!-- Popup chat is the only chat entry point -->
        @endauth
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

@auth
    @unless(request()->routeIs('chat.user.index'))
        <style>
            #user-chat-toggle {
                position: fixed;
                right: 24px;
                bottom: 24px;
                z-index: 2001;
                width: 62px;
                height: 62px;
                border: none;
                border-radius: 50%;
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: #fff;
                font-size: 26px;
                box-shadow: 0 12px 25px rgba(37, 99, 235, 0.32);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                line-height: 1;
                position: fixed;
            }
            .user-chat-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 20px;
                height: 20px;
                padding: 0 6px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                font-size: 11px;
                font-weight: bold;
                line-height: 20px;
                text-align: center;
            }
            .user-chat-popup {
                position: fixed;
                right: 24px;
                bottom: 92px;
                z-index: 2000;
                width: min(760px, calc(100vw - 24px));
                color: #1e293b;
            }
            .user-chat-window {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.2);
            }
            .user-chat-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 18px;
                background: linear-gradient(135deg, #0f172a, #1e293b);
                color: #fff;
            }
            .user-chat-header h2 {
                margin: 0;
                font-size: 18px;
            }
            .user-chat-header small {
                display: block;
                margin-top: 2px;
                color: #cbd5e1;
            }
            #user-chat-close {
                width: 32px;
                height: 32px;
                border: 0;
                border-radius: 50%;
                background: rgba(255,255,255,0.12);
                color: #fff;
                font-size: 28px;
                line-height: 1;
                cursor: pointer;
            }
            .user-chat-layout {
                display: flex;
                flex-direction: column;
                height: 500px;
                background: #f3f4f6;
            }
            .user-chat-messages {
                flex: 1;
                min-height: 0;
                margin: 0;
                padding: 18px 18px 12px;
                overflow-y: auto;
                list-style: none;
                background: #f3f4f6;
            }
            .user-chat-message {
                display: flex;
                margin-bottom: 14px;
                width: 100%;
            }
            .user-chat-message.is-mine { justify-content: flex-end; }
            .user-chat-bubble {
                max-width: min(68%, 540px);
                padding: 12px 14px 10px;
                border-radius: 18px 18px 18px 6px;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            }
            .user-chat-message.is-mine .user-chat-bubble {
                border-radius: 18px 18px 6px 18px;
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: #fff;
                border-color: transparent;
            }
            .user-chat-author {
                display: block;
                margin-bottom: 4px;
                color: #64748b;
                font-size: 12px;
                font-weight: 700;
            }
            .user-chat-message.is-mine .user-chat-author { color: rgba(255, 255, 255, 0.9); }
            .user-chat-body {
                margin: 0;
                white-space: pre-wrap;
                overflow-wrap: anywhere;
                line-height: 1.5;
                font-size: 14px;
            }
            .user-chat-time {
                display: block;
                margin-top: 6px;
                color: #94a3b8;
                font-size: 11px;
            }
            .user-chat-message.is-mine .user-chat-time { color: rgba(255, 255, 255, 0.8); }
            .user-chat-compose {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 104px;
                gap: 12px;
                align-items: end;
                padding: 12px 16px 14px;
                border-top: 1px solid #dfe3ea;
                background: #fff;
            }
            .user-chat-compose textarea {
                min-height: 52px;
                max-height: 120px;
                resize: vertical;
                padding: 12px 14px;
                border: 1px solid #d1d5db;
                border-radius: 12px;
                font: inherit;
                line-height: 1.4;
                background: #fff;
            }
            .user-chat-compose textarea:focus {
                border-color: #2563eb;
                outline: 2px solid rgba(37, 99, 235, 0.15);
            }
            .user-chat-compose button {
                min-height: 52px;
                padding: 0 12px;
                border: 0;
                border-radius: 12px;
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: #fff;
                font-weight: 700;
                cursor: pointer;
            }
            .user-chat-compose button:disabled { cursor: wait; opacity: .6; }
            .user-chat-compose-meta {
                grid-column: 1 / -1;
                display: flex;
                justify-content: space-between;
                color: #64748b;
                font-size: 12px;
                padding: 0 2px;
            }
            .user-chat-alert { margin: 0; padding: 11px 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; }
            .user-chat-alert[hidden] { display: none; }
            @media (max-width: 640px) {
                .user-chat-popup { right: 12px; left: 12px; width: auto; }
                .user-chat-layout { height: 440px; }
                .user-chat-compose { flex-wrap: wrap; }
                .user-chat-compose textarea { flex-basis: calc(100% - 62px); }
                .user-chat-compose button { width: 50px; padding: 0; font-size: 0; }
                .user-chat-compose button::after { content: '➤'; font-size: 18px; }
                .user-chat-compose-meta { width: 100%; }
            }
        </style>

        <button id="user-chat-toggle" type="button" aria-label="Mở hộp chat user">
            💬
            <span class="user-chat-badge" data-chat-unread-count>0</span>
        </button>

        <section
            id="user-chat-popup"
            class="user-chat-popup"
            data-chat-page="user"
            data-chat-user-id="{{ auth()->id() }}"
            data-overview-url="{{ route('chat.user.overview') }}"
            data-messages-url="{{ route('chat.user.messages') }}"
            data-send-url="{{ route('chat.user.messages.store') }}"
            data-read-url="{{ route('chat.user.read') }}"
            data-status-url="{{ route('chat.user.admin-status') }}"
            data-channel="chat.user.{{ auth()->id() }}"
            data-event="chat.message.sent"
            data-max-length="2000"
            style="display: none;"
        >
            <div class="user-chat-window">
                <header class="user-chat-header">
                    <div>
                        <h2>Nhắn tin với admin</h2>
                        <small>Hỗ trợ khách hàng</small>
                    </div>
                    <button id="user-chat-close" type="button" aria-label="Đóng hộp chat">×</button>
                </header>

                <div class="user-chat-layout">
                    <ol class="user-chat-messages" data-chat-messages aria-live="polite" aria-label="Lịch sử tin nhắn"></ol>
                    <p class="chat-empty" data-chat-empty>Chưa có tin nhắn. Bạn hãy gửi lời nhắn đầu tiên nhé.</p>

                    <form class="user-chat-compose" data-chat-form>
                        <textarea name="body" data-chat-input maxlength="2000" placeholder="Viết tin nhắn..." aria-label="Nội dung tin nhắn" required></textarea>
                        <button type="submit" data-chat-submit>Gửi</button>
                        <div class="user-chat-compose-meta">
                            <span>Tin nhắn tối đa 2000 ký tự.</span>
                            <span data-chat-count>0/2000</span>
                        </div>
                    </form>
                </div>
            </div>

            <div class="user-chat-alert" data-chat-error role="alert" hidden></div>
        </section>

        <script>
            (function () {
                const toggleButton = document.getElementById('user-chat-toggle');
                const popup = document.getElementById('user-chat-popup');
                const closeButton = document.getElementById('user-chat-close');

                if (!toggleButton || !popup || !closeButton) return;

                const openPopup = () => { popup.style.display = 'block'; };
                const closePopup = () => { popup.style.display = 'none'; };

                toggleButton.addEventListener('click', openPopup);
                closeButton.addEventListener('click', closePopup);
            })();
        </script>
    @endunless
@endauth

@vite(['resources/js/app.js'])

</body>
</html>
