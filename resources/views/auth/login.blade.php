@extends('layouts.auth', ['title' => 'Đăng nhập', 'showBrand' => true])
@section('content')
<h1>Chào mừng trở lại</h1>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">
    @csrf
    <label for="email">Địa chỉ email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
    <label for="password">Mật khẩu</label>
    <div class="password-field" data-password-field>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg></button>
    </div>
    <div class="auth-options">
        <label class="remember-label"><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label>
        <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
    </div>
    <button type="submit">Đăng nhập</button>
</form>
<div class="links"><span>Chưa có tài khoản?</span><a href="{{ route('register') }}">Tạo tài khoản</a></div>
@endsection
