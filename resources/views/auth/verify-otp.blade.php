@extends('layouts.auth', ['title' => 'Xác minh mã'])

@section('content')
    <h1>Xác minh mã</h1>

    @if(session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.verify') }}">
        @csrf

        <label>Email</label>
        <input type="email" name="email" value="{{ session('email', old('email')) }}" required>

        <label>Mã 6 số</label>
        <input name="otp" inputmode="numeric" maxlength="6" required>

        <button type="submit">Tiếp tục</button>
    </form>
@endsection