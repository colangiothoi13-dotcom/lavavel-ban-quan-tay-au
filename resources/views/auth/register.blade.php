@extends('layouts.auth', ['title' => 'Đăng ký'])
@section('content')
<h1>Đăng ký tài khoản</h1>@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register.store') }}">@csrf
<label>Họ và tên</label><input name="name" value="{{ old('name') }}" required>
<div class="grid"><div><label>Giới tính</label><select name="gender"><option value="">Chọn</option><option>Nam</option><option>Nữ</option><option>Khác</option></select></div><div><label>Ngày sinh</label><input type="date" name="birth_date" value="{{ old('birth_date') }}"></div></div>
<label>Số điện thoại</label><input name="phone" value="{{ old('phone') }}">
<label>Email</label><input type="email" name="email" value="{{ old('email') }}" required>
<label>Mật khẩu</label><input type="password" name="password" required minlength="8"><label>Nhập lại mật khẩu</label><input type="password" name="password_confirmation" required>
<button type="submit">Đăng ký</button></form><div class="links"><a href="{{ route('login') }}">Đã có tài khoản? Đăng nhập</a></div>
@endsection
