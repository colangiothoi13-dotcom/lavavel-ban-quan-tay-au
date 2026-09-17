@extends('layouts.auth', ['title' => 'Xác minh mã'])
@section('content')
<h1>Xác minh mã</h1>
<p class="auth-intro">Nhập mã 6 số đã được gửi đến email của bạn.</p>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.verify') }}">
    @csrf
    <label for="email">Email</label><input id="email" type="email" name="email" value="{{ session('email', old('email')) }}" required>
    <label for="otp">Mã 6 số</label><input id="otp" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus>
    <button type="submit">Tiếp tục</button>
</form>
<div class="links"><a href="{{ route('password.request') }}">Gửi lại mã</a></div>
@endsection
