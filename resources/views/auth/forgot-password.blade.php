@extends('layouts.auth', ['title' => 'Quên mật khẩu'])
@section('content')<h1>Quên mật khẩu</h1><p>Nhập email để nhận mã xác minh.</p>@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.email') }}">@csrf<label>Email</label><input type="email" name="email" required><button>Gửi mã xác minh</button></form><div class="links"><a href="{{ route('login') }}">Quay lại đăng nhập</a></div>@endsection
