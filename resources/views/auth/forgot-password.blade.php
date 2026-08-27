@extends('layouts.auth', ['title' => 'Quên mật khẩu'])
@section('content')
<h1>Quên mật khẩu</h1>
<p class="auth-intro">Nhập email để nhận mã xác minh.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
    <button type="submit">Gửi mã xác minh</button>
</form>
<div class="links"><a href="{{ route('login') }}">Quay lại đăng nhập</a></div>
@endsection
