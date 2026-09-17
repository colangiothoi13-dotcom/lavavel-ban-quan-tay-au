@extends('layouts.auth', ['title' => 'Đăng ký'])
@section('content')
<h1>Đăng ký tài khoản</h1>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register.store') }}">
    @csrf
    <label for="name">Họ và tên</label><input id="name" name="name" value="{{ old('name') }}" required>
    <div class="form-grid">
        <div><label for="gender">Giới tính</label><select id="gender" name="gender"><option value="">Chọn</option><option>Nam</option><option>Nữ</option><option>Khác</option></select></div>
        <div><label for="birth_date">Ngày sinh</label><input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}"></div>
    </div>
    <label for="phone">Số điện thoại</label><input id="phone" name="phone" value="{{ old('phone') }}" placeholder="0912345678 hoặc +84912345678" pattern="^(0|\+84)(3|5|7|8|9)[0-9]{8}$" required>
    <label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
    <label for="password">Mật khẩu</label>
    <div class="password-field" data-password-field>
        <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg></button>
    </div>
    <label for="password_confirmation">Nhập lại mật khẩu</label>
    <div class="password-field" data-password-field>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg></button>
    </div>
    <button type="submit">Đăng ký</button>
</form>
<div class="links"><a href="{{ route('login') }}">Đã có tài khoản? Đăng nhập</a></div>
@endsection
