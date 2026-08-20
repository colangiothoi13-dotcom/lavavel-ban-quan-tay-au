@extends('layouts.auth', ['title' => 'Đặt lại mật khẩu'])
@section('content')<h1>Đặt lại mật khẩu</h1>@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}">@csrf<label>Email</label><input type="email" name="email" value="{{ session('email', old('email')) }}" required><label>Mật khẩu mới</label><input type="password" name="password" minlength="8" required><label>Nhập lại mật khẩu</label><input type="password" name="password_confirmation" required><button>Đổi mật khẩu</button></form>@endsection
