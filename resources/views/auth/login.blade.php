@extends('layouts.auth', ['title' => 'Đăng nhập'])
@section('content')
<h1>Đăng nhập</h1>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">@csrf
<label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus>
<label>Mật khẩu</label><input type="password" name="password" required>
<label><input type="checkbox" name="remember" value="1" style="width:auto"> Ghi nhớ đăng nhập</label>
<button type="submit">Đăng nhập</button>
</form>
<div class="links"><a href="{{ route('register') }}">Tạo tài khoản</a><a href="{{ route('password.request') }}">Quên mật khẩu?</a></div>
@endsection
