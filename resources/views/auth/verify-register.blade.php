@extends('layouts.auth', ['title' => 'Xác minh đăng ký'])
@section('content')
<h1>Xác minh email</h1>
<p class="auth-intro">Nhập mã 6 số đã được gửi đến email của bạn.</p>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register.verify.submit') }}">
    @csrf
    <label for="otp">Mã xác minh</label><input id="otp" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus>
    <button type="submit">Xác nhận đăng ký</button>
</form>
<div class="links">
    <form method="POST" action="{{ route('register.verify.resend') }}">
        @csrf
        <button class="link-button" type="submit">Gửi lại mã xác minh</button>
    </form>
    <a href="{{ route('register') }}">Đăng ký lại</a>
</div>
@endsection
