@extends('layouts.app')

@section('content')
<style>
    .welcome-title {
        font-size: 32px;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 12px;
    }
    .welcome-text {
        font-size: 18px;
        color: #4a5568;
        margin-bottom: 8px;
    }
    .role-badge {
        font-size: 18px;
        color: #2d3748;
        margin-bottom: 24px;
    }
    .admin-links {
        display: flex;
        gap: 16px;
        margin-top: 20px;
    }
    .admin-btn {
        display: inline-block;
        padding: 10px 18px;
        background-color: #3182ce;
        color: #ffffff;
        text-decoration: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 500;
        transition: background-color 0.2s;
    }
    .admin-btn:hover {
        background-color: #2b6cb0;
        color: #ffffff;
    }
</style>

<div class="container" style="padding: 20px;">
    @if(session('status'))
        <div style="background:#dcfce7;color:#166534;padding:12px 16px;border-radius:6px;margin-bottom:20px;">
            {{ session('status') }}
        </div>
    @endif
    <h1 class="welcome-title">Xin chào, Quản trị viên</h1>
    <p class="welcome-text">Bạn đang ở khu vực quản trị được bảo vệ.</p>
    <p class="role-badge">Vai trò: <strong style="color: #2b6cb0;">{{ auth()->user()->role }}</strong></p>
    <a href="{{ route('home') }}" class="admin-btn">Xem trang chủ</a>

</div>
@endsection