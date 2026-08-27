@extends('layouts.auth', ['title' => 'Đặt lại mật khẩu'])
@section('content')
<h1>Đặt lại mật khẩu</h1>
<p class="auth-intro">Tạo mật khẩu mới cho tài khoản của bạn.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <label for="email">Email</label><input id="email" type="email" name="email" value="{{ session('email', old('email')) }}" required>
    <label for="password">Mật khẩu mới</label>
    <div class="password-field" data-password-field>
        <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg></button>
    </div>
    <label for="password_confirmation">Nhập lại mật khẩu</label>
    <div class="password-field" data-password-field>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg></button>
    </div>
    <button type="submit">Đổi mật khẩu</button>
</form>
<div class="links"><a href="{{ route('login') }}">Quay lại đăng nhập</a></div>
@endsection
