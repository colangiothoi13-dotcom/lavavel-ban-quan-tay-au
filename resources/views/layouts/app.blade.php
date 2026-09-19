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
        
        .admin-content-area { min-height: 0; flex: 1; padding: 1px 8px 0; overflow-y: auto; }
        .admin-card { background-color: #ffffff; padding: 12px; border-radius: 4px; min-height: 100%; border-top: 4px solid #333; margin-top: 1px; }

        /* ================= PHẦN DÀNH CHO USER ================= */
        .user-shell { display: flex; gap: 2px; width: 100%; min-height: 100vh; background: #e2e8f0; }
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
        .user-main { width: 100%; min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; background-color: #f1f5f9; }
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
        .user-content-area {
            flex: 1;
            padding: 0;
            overflow-y: auto;
        }
        .user-card {
            background-color: #ffffff;
            padding: 0;
            border-radius: 20px;
            min-height: calc(100vh - 70px);
            box-shadow: none;
            border: 1px solid #e2e8f0;
            margin: 0;
            overflow: hidden;
        }
        .user-flash-status { margin: 16px; padding: 12px 16px; border-radius: 5px; background: #e3f6e9; color: #166534; }

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
            .user-content-area { padding: 0; }
            .user-card { padding: 0; min-height: auto; margin: 0; }
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
                <li><a href="{{ route('admin.users.index') }}" class="{{ Request::is('admin/users*') ? 'active' : '' }}">Người dùng</a></li>
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

        @vite(['resources/js/app.js'])

        @unless(request()->routeIs('chat.admin.index'))
            <style>
                #chat-toggle {
                    position: fixed;
                    right: 24px;
                    bottom: 24px;
                    z-index: 2000;
                    width: 58px;
                    height: 58px;
                    border: none;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    color: #fff;
                    font-size: 24px;
                    box-shadow: 0 12px 25px rgba(37, 99, 235, 0.32);
                    cursor: pointer;
                }
                .admin-chat-badge {
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
                .admin-chat-page {
                    position: fixed;
                    right: 24px;
                    bottom: 92px;
                    z-index: 1999;
                    width: min(760px, calc(100vw - 24px));
                    color: #1e293b;
                }
                .admin-chat-window {
                    border: 1px solid #e2e8f0;
                    border-radius: 16px;
                    overflow: hidden;
                    background: #fff;
                    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.2);
                }
                .admin-chat-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 14px 18px;
                    background: linear-gradient(135deg, #0f172a, #1e293b);
                    color: #fff;
                }
                .admin-chat-header h2 {
                    margin: 0;
                    font-size: 18px;
                }
                .admin-chat-header small {
                    display: block;
                    margin-top: 2px;
                    color: #cbd5e1;
                }
                #chat-close {
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
                .admin-chat-layout {
                    display: grid;
                    grid-template-columns: minmax(200px, 28%) minmax(0, 1fr);
                    height: 500px;
                    background: #f8fafc;
                }
                .admin-chat-conversations {
                    padding: 12px;
                    border-right: 1px solid #e2e8f0;
                    background: #fff;
                    overflow-y: auto;
                }
                .admin-chat-conversations h3 {
                    margin: 4px 8px 12px;
                    font-size: 15px;
                }
                .admin-chat-list {
                    display: grid;
                    gap: 7px;
                }
                .admin-chat-conversation {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid transparent;
                    border-radius: 10px;
                    background: transparent;
                    color: inherit;
                    text-align: left;
                    cursor: pointer;
                }
                .admin-chat-conversation:hover, .admin-chat-conversation.is-selected {
                    border-color: #dbeafe;
                    background: #eff6ff;
                }
                .admin-chat-conversation.is-unread .admin-chat-conversation-name,
                .admin-chat-conversation.is-unread .admin-chat-conversation-preview {
                    color: #1e293b;
                    font-weight: 700;
                }
                .admin-chat-conversation-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 8px;
                }
                .admin-chat-conversation-name {
                    overflow: hidden;
                    font-weight: 700;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .admin-chat-conversation-preview {
                    display: block;
                    margin-top: 5px;
                    overflow: hidden;
                    color: #64748b;
                    font-size: 12px;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .admin-chat-conversation-time {
                    margin-top: 5px;
                    color: #94a3b8;
                    font-size: 11px;
                }
                .admin-chat-unread {
                    min-width: 22px;
                    padding: 2px 6px;
                    border-radius: 999px;
                    background: #dc2626;
                    color: #fff;
                    font-size: 11px;
                    text-align: center;
                }
                .admin-chat-unread[hidden], .admin-chat-empty[hidden] {
                    display: none;
                }
                .admin-chat-empty {
                    margin: 34px 8px;
                    color: #64748b;
                    font-size: 13px;
                    text-align: center;
                }
                .admin-chat-thread {
                    display: flex;
                    min-width: 0;
                    min-height: 0;
                    flex-direction: column;
                }
                .admin-chat-thread-heading {
                    padding: 16px 20px;
                    border-bottom: 1px solid #e2e8f0;
                    background: #fff;
                }
                .admin-chat-thread-heading h2 {
                    margin: 0 0 4px;
                    font-size: 18px;
                }
                .admin-chat-thread-heading p {
                    margin: 0;
                    color: #64748b;
                    font-size: 13px;
                }
                .admin-chat-messages {
                    flex: 1;
                    min-height: 200px;
                    margin: 0;
                    padding: 16px 18px 8px;
                    overflow-y: auto;
                    list-style: none;
                }
                .admin-chat-message {
                    display: flex;
                    margin-bottom: 14px;
                }
                .admin-chat-message.is-mine {
                    justify-content: flex-end;
                }
                .admin-chat-bubble {
                    max-width: min(78%, 620px);
                    padding: 11px 14px;
                    border-radius: 14px 14px 14px 4px;
                    background: #fff;
                    box-shadow: 0 2px 8px rgba(15, 23, 42, .06);
                }
                .admin-chat-message.is-mine .admin-chat-bubble {
                    border-radius: 14px 14px 4px 14px;
                    background: #2563eb;
                    color: #fff;
                }
                .admin-chat-author {
                    display: block;
                    margin-bottom: 4px;
                    color: #64748b;
                    font-size: 12px;
                    font-weight: 700;
                }
                .admin-chat-message.is-mine .admin-chat-author {
                    color: #dbeafe;
                }
                .admin-chat-body {
                    margin: 0;
                    white-space: pre-wrap;
                    overflow-wrap: anywhere;
                    line-height: 1.5;
                }
                .admin-chat-time {
                    display: block;
                    margin-top: 6px;
                    color: #94a3b8;
                    font-size: 11px;
                }
                .admin-chat-message.is-mine .admin-chat-time {
                    color: #dbeafe;
                }
                .admin-chat-compose {
                    display: flex;
                    align-items: flex-end;
                    gap: 12px;
                    padding: 12px 16px 16px;
                    border-top: 1px solid #e2e8f0;
                    background: #fff;
                }
                .admin-chat-compose textarea {
                    flex: 1;
                    min-height: 48px;
                    max-height: 120px;
                    resize: vertical;
                    padding: 12px 14px;
                    border: 1px solid #cbd5e1;
                    border-radius: 10px;
                    font: inherit;
                    line-height: 1.4;
                }
                .admin-chat-compose textarea:focus {
                    border-color: #2563eb;
                    outline: 2px solid rgba(37, 99, 235, .18);
                }
                .admin-chat-compose button {
                    min-height: 48px;
                    padding: 0 20px;
                    border: 0;
                    border-radius: 10px;
                    background: #2563eb;
                    color: #fff;
                    font-weight: 700;
                    cursor: pointer;
                }
                .admin-chat-compose button:disabled {
                    cursor: wait;
                    opacity: .6;
                }
                .admin-chat-compose-meta {
                    display: flex;
                    justify-content: space-between;
                    color: #64748b;
                    font-size: 12px;
                }
                .admin-chat-alert {
                    margin: 0;
                    padding: 11px 14px;
                    border: 1px solid #fecaca;
                    border-radius: 8px;
                    background: #fef2f2;
                    color: #991b1b;
                }
                .admin-chat-alert[hidden] {
                    display: none;
                }
                @media (max-width: 760px) {
                    .admin-chat-page {
                        right: 12px;
                        left: 12px;
                        width: auto;
                    }
                    .admin-chat-layout {
                        grid-template-columns: 1fr;
                        height: 520px;
                    }
                    .admin-chat-conversations {
                        max-height: 180px;
                        border-right: 0;
                        border-bottom: 1px solid #e2e8f0;
                    }
                    .admin-chat-compose {
                        flex-wrap: wrap;
                    }
                    .admin-chat-compose textarea {
                        flex-basis: calc(100% - 62px);
                    }
                    .admin-chat-compose button {
                        width: 50px;
                        padding: 0;
                        font-size: 0;
                    }
                    .admin-chat-compose button::after {
                        content: '➤';
                        font-size: 18px;
                    }
                    .admin-chat-compose-meta {
                        width: 100%;
                    }
                }
            </style>

            <button id="chat-toggle" type="button" aria-label="Mở hộp chat">
                💬
                <span class="admin-chat-badge" data-chat-toggle-unread>0</span>
            </button>

            <section
                id="chat-popup"
                class="admin-chat-page"
                data-chat-page="admin"
                data-admin-id="{{ auth()->id() }}"
                data-conversations-url="{{ route('chat.admin.conversations') }}"
                data-messages-template="{{ route('chat.admin.messages', ['conversation' => '__CONVERSATION__']) }}"
                data-send-template="{{ route('chat.admin.messages.store', ['conversation' => '__CONVERSATION__']) }}"
                data-read-template="{{ route('chat.admin.read', ['conversation' => '__CONVERSATION__']) }}"
                data-presence-url="{{ route('chat.admin.presence') }}"
                data-admin-channel="chat.admins"
                data-event="chat.message.sent"
                data-max-length="2000"
                style="display: none;"
            >
                <div class="admin-chat-window">
                    <header class="admin-chat-header">
                        <div>
                            <h2>Chat Admin</h2>
                            <small>Hỗ trợ khách hàng</small>
                        </div>
                        <button id="chat-close" type="button" aria-label="Đóng hộp chat">×</button>
                    </header>

                    <div class="admin-chat-badge admin-chat-badge--popup" data-admin-unread>0</div>

                    <div class="admin-chat-layout">
                        <aside class="admin-chat-conversations" aria-label="Danh sách cuộc hội thoại">
                            <h3>Khách hàng</h3>
                            <div id="user-list" class="admin-chat-list" data-admin-conversations></div>
                            <p class="admin-chat-empty" data-admin-empty>Chưa có cuộc hội thoại nào.</p>
                        </aside>

                        <section class="admin-chat-thread" aria-labelledby="admin-chat-selected-user">
                            <header class="admin-chat-thread-heading">
                                <h2 id="admin-chat-selected-user" data-admin-selected-user>Chọn một khách hàng</h2>
                                <p data-admin-selected-hint>Những tin nhắn mới sẽ xuất hiện theo thời gian thực.</p>
                            </header>

                            <ol id="chat-messages" class="admin-chat-messages" data-chat-messages aria-live="polite" aria-label="Tin nhắn trong cuộc hội thoại"></ol>
                            <p class="admin-chat-empty" data-chat-empty>Chọn cuộc hội thoại để bắt đầu hỗ trợ.</p>

                            <form class="admin-chat-compose" data-chat-form>
                                <textarea id="chat-input" name="body" data-chat-input maxlength="2000" placeholder="Nhập tin nhắn..." aria-label="Nội dung trả lời" required disabled></textarea>
                                <button id="send-btn" type="submit" data-chat-submit disabled>Gửi</button>
                                <div class="admin-chat-compose-meta">
                                    <span>Tin nhắn tối đa 2000 ký tự.</span>
                                    <span data-chat-count>0/2000</span>
                                </div>
                            </form>
                        </section>
                    </div>
                </div>

                <div class="admin-chat-alert" data-chat-error role="alert" hidden></div>
            </section>

            <script>
                (function () {
                    const toggleButton = document.getElementById('chat-toggle');
                    const popup = document.getElementById('chat-popup');
                    const closeButton = document.getElementById('chat-close');

                    if (!toggleButton || !popup || !closeButton) return;

                    const openPopup = () => {
                        popup.style.display = 'block';
                    };

                    const closePopup = () => {
                        popup.style.display = 'none';
                    };

                    toggleButton.addEventListener('click', openPopup);
                    closeButton.addEventListener('click', closePopup);
                })();
            </script>
        @endunless

    @else
        @auth
        @unless(request()->routeIs('chat.user.index'))
            @vite(['resources/js/app.js'])
            <style>
                #user-chat-toggle {
                    position: fixed;
                    right: 24px;
                    bottom: 24px;
                    z-index: 2001;
                    width: 58px;
                    height: 58px;
                    border: none;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #111827, #1f2937);
                    color: #fff;
                    font-size: 24px;
                    box-shadow: 0 12px 25px rgba(15, 23, 42, 0.26);
                    cursor: pointer;
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
                    bottom: 96px;
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
                    background: #f8fafc;
                }
                .user-chat-product-context { flex: 0 0 auto; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; background: #eff6ff; }
                .user-chat-product-context[hidden] { display: none; }
                .user-chat-product-link { display: flex; align-items: center; gap: 10px; color: #1e293b; text-decoration: none; }
                .user-chat-product-link:hover .user-chat-product-copy strong { text-decoration: underline; }
                .user-chat-product-link img { width: 48px; height: 48px; flex: 0 0 48px; border: 1px solid #bfdbfe; border-radius: 7px; background: #fff; object-fit: cover; }
                .user-chat-product-copy { display: grid; gap: 4px; min-width: 0; }
                .user-chat-product-copy strong { overflow: hidden; color: #1e3a8a; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
                .user-chat-product-copy span { color: #2563eb; font-size: 12px; font-weight: 700; }
                .user-chat-messages {
                    flex: 1;
                    min-height: 0;
                    margin: 0;
                    padding: 16px 18px;
                    overflow-y: auto;
                    list-style: none;
                    background: #f8fafc;
                }
                .user-chat-message { display: flex; margin-bottom: 14px; }
                .user-chat-message.is-mine { justify-content: flex-end; }
                .user-chat-bubble { max-width: min(78%, 620px); padding: 11px 14px; border-radius: 14px 14px 14px 4px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42,.08); }
                .user-chat-message.is-mine .user-chat-bubble { border-color: #2563eb; border-radius: 14px 14px 4px 14px; background: #2563eb; color: #fff; }
                .user-chat-author { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 700; }
                .user-chat-message.is-mine .user-chat-author { color: #fff; }
                .user-chat-body { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
                .user-chat-time { display: block; margin-top: 6px; color: #94a3b8; font-size: 11px; }
                .user-chat-message.is-mine .user-chat-time { color: #dbeafe; }
                .user-chat-compose { display: flex; align-items: flex-end; gap: 12px; padding: 12px 16px 16px; border-top: 1px solid #e2e8f0; background: #fff; }
                .user-chat-compose textarea { flex: 1; min-height: 48px; max-height: 120px; resize: vertical; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; line-height: 1.4; }
                .user-chat-compose textarea:focus { border-color: #b4860b; outline: 2px solid rgba(180, 134, 11, 0.18); }
                .user-chat-compose button { min-height: 48px; padding: 0 20px; border: 0; border-radius: 10px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
                .user-chat-compose button:disabled { cursor: wait; opacity: .6; }
                .user-chat-compose-meta { display: flex; justify-content: space-between; color: #64748b; font-size: 12px; }
                .user-chat-alert { margin: 0; padding: 11px 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; }
                .user-chat-alert[hidden] { display: none; }
                @media (max-width: 640px) { .user-chat-popup { right: 12px; left: 12px; width: auto; } .user-chat-layout { height: 440px; } .user-chat-compose { flex-wrap: wrap; } .user-chat-compose textarea { flex-basis: calc(100% - 62px); } .user-chat-compose button { width: 50px; padding: 0; font-size: 0; } .user-chat-compose button::after { content: '➤'; font-size: 18px; } .user-chat-compose-meta { width: 100%; } }
            </style>

            <button id="user-chat-toggle" type="button" aria-label="Mở hộp chat user">
                💬
                <span class="user-chat-badge" data-chat-unread-count>0</span>
            </button>

            <section
                id="user-chat-popup"
                class="user-chat-popup"
                data-chat-page="user"
                data-chat-class-prefix="user-chat"
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
                        <article class="user-chat-product-context" data-chat-product-context hidden>
                            <a class="user-chat-product-link" data-chat-product-link href="#">
                                <img data-chat-product-image src="" alt="">
                                <span class="user-chat-product-copy">
                                    <strong data-chat-product-name></strong>
                                    <span data-chat-product-price></span>
                                </span>
                            </a>
                        </article>
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

                    const productContext = popup.querySelector('[data-chat-product-context]');
                    const productImage = popup.querySelector('[data-chat-product-image]');
                    const productName = popup.querySelector('[data-chat-product-name]');
                    const productPrice = popup.querySelector('[data-chat-product-price]');
                    const productLink = popup.querySelector('[data-chat-product-link]');
                    const input = popup.querySelector('[data-chat-input]');
                    let consultPrefix = '';

                    const openPopup = () => { popup.style.display = 'block'; };
                    const clearProductContext = () => {
                        if (input && consultPrefix && input.value.startsWith(consultPrefix)) {
                            input.value = input.value.slice(consultPrefix.length).trimStart();
                        }
                        consultPrefix = '';
                        if (productContext) productContext.hidden = true;
                        if (productImage) {
                            productImage.removeAttribute('src');
                            productImage.alt = '';
                        }
                        if (productName) productName.textContent = '';
                        if (productPrice) productPrice.textContent = '';
                        if (productLink) productLink.href = '#';
                    };
                    const closePopup = () => {
                        clearProductContext();
                        popup.style.display = 'none';
                    };

                    const showProductContext = (trigger) => {
                        if (!input) return;

                        const name = trigger.dataset.consultProductName?.trim();
                        if (!name) return;
                        const messagePrefix = `Mình muốn hỏi tư vấn về sản phẩm ${name}. `;
                        const currentValue = input.value.trim();

                        if (!currentValue || (consultPrefix && input.value.startsWith(consultPrefix))) {
                            input.value = messagePrefix;
                        }
                        consultPrefix = messagePrefix;
                        if (productImage) {
                            productImage.src = trigger.dataset.consultProductImage || '';
                            productImage.alt = name;
                        }
                        if (productName) productName.textContent = name;
                        if (productPrice) productPrice.textContent = trigger.dataset.consultProductPrice || '';
                        if (productLink) productLink.href = trigger.dataset.consultProductUrl || '#';
                        if (productContext) productContext.hidden = false;
                        input.focus();
                        input.selectionStart = input.value.length;
                        input.selectionEnd = input.value.length;
                    };

                    toggleButton.addEventListener('click', openPopup);
                    closeButton.addEventListener('click', closePopup);

                    document.addEventListener('click', (event) => {
                        const trigger = event.target.closest('[data-consult-trigger]');
                        if (!trigger) return;

                        if (!popup) {
                            window.location.href = trigger.dataset.consultLoginUrl || '{{ route('login') }}';
                            return;
                        }

                        openPopup();
                        showProductContext(trigger);
                    });
                })();
            </script>
        @endunless
        @endauth

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
                    <li><a href="{{ route('shop.wishlist.index') }}" class="{{ Request::is('user/yeu-thich*') ? 'active' : '' }}"><span class="menu-icon">❤️</span><span class="nav-label">Yêu thích</span></a></li>
                    <li><a href="{{ route('shop.history.index') }}" class="{{ Request::is('user/lich-su-duyet*') ? 'active' : '' }}"><span class="menu-icon">🕘</span><span class="nav-label">Lịch sử duyệt</span></a></li>
                    <li><a href="{{ route('shop.recommendations') }}" class="{{ Request::is('user/goi-y-ca-nhan*') ? 'active' : '' }}"><span class="menu-icon">✨</span><span class="nav-label">Gợi ý cho bạn</span></a></li>
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
                        @if(session('status'))
                            <div class="user-flash-status" role="status">{{ session('status') }}</div>
                        @endif
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
            const backdrop = document.querySelector('[data-user-menu-backdrop]');
            if (!toggle || !menu || !backdrop || !shell) return;

            const STORAGE_KEY = 'lavabeo.userSidebarCollapsed';

            function isMobile() {
                return window.innerWidth <= 900;
            }

            function saveSidebarState(collapsed) {
                try {
                    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
                } catch (error) {
                    // localStorage có thể bị chặn; không làm vỡ giao diện.
                }
            }

            function readSidebarState() {
                try {
                    const raw = localStorage.getItem(STORAGE_KEY);
                    return raw === null ? true : raw === '1';
                } catch (error) {
                    return true;
                }
            }

            function syncDesktopState() {
                const collapsed = shell.classList.contains('user-shell--collapsed');
                toggle.setAttribute('aria-expanded', String(!collapsed));
                toggle.setAttribute('aria-label', collapsed ? 'Mở menu' : 'Thu gọn menu');
                menu.setAttribute('aria-hidden', 'false');
                backdrop.classList.remove('is-open');
                backdrop.tabIndex = -1;
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

                const nextCollapsed = !open;
                shell.classList.toggle('user-shell--collapsed', nextCollapsed);
                saveSidebarState(nextCollapsed);
                syncDesktopState();
            }

            shell.classList.toggle('user-shell--collapsed', readSidebarState());
            if (isMobile()) {
                menu.setAttribute('aria-hidden', 'true');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Mở menu');
            } else {
                syncDesktopState();
            }

            toggle.addEventListener('click', function () {
                if (isMobile()) {
                    setMenu(toggle.getAttribute('aria-expanded') !== 'true');
                    return;
                }

                const nextCollapsed = !shell.classList.contains('user-shell--collapsed');
                shell.classList.toggle('user-shell--collapsed', nextCollapsed);
                saveSidebarState(nextCollapsed);
                syncDesktopState();
            });

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
                    toggle.setAttribute('aria-label', 'Mở menu');
                    backdrop.tabIndex = -1;
                    return;
                }

                shell.classList.toggle('user-shell--collapsed', readSidebarState());
                syncDesktopState();
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
