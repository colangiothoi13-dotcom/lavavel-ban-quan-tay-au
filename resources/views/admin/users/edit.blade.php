@extends('layouts.app')

@section('content')
<div class="form-shell">
    <div class="form-header">
        <div>
            <p class="eyebrow">Người dùng</p>
            <h1>Cập nhật người dùng</h1>
        </div>
        <a href="{{ route('admin.users.index') }}" class="secondary-link">← Quay lại</a>
    </div>

    <form class="user-form" method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="field-grid">
            <div class="field">
                <label for="name">Họ và tên</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                @error('email')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="phone">Số điện thoại</label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                @error('phone')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="gender">Giới tính</label>
                <select id="gender" name="gender">
                    <option value="">-- Chọn --</option>
                    <option value="male" @selected(old('gender', $user->gender) === 'male')>Nam</option>
                    <option value="female" @selected(old('gender', $user->gender) === 'female')>Nữ</option>
                    <option value="other" @selected(old('gender', $user->gender) === 'other')>Khác</option>
                </select>
                @error('gender')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="role">Vai trò</label>
                <select id="role" name="role" required>
                    <option value="user" @selected(old('role', $user->role) === 'user')>Khách hàng</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                </select>
                @error('role')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="password">Mật khẩu mới</label>
                <input id="password" name="password" type="password" placeholder="Để trống nếu không đổi">
                @error('password')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Xác nhận mật khẩu mới</label>
                <input id="password_confirmation" name="password_confirmation" type="password">
            </div>
        </div>

        <div class="action-row">
            <button type="submit" class="primary-btn">Cập nhật</button>
        </div>
    </form>
</div>

<style>
.form-shell { color: #1f2937; }
.form-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.eyebrow { margin: 0 0 8px; color: #f4511e; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; }
.form-header h1 { margin: 0; font-size: 28px; }
.secondary-link { color: #334155; text-decoration: none; font-weight: 600; }
.user-form { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
.field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.field { display: flex; flex-direction: column; gap: 8px; }
.field label { font-weight: 700; color: #475569; font-size: 13px; }
.field input, .field select { padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; }
.field input:focus, .field select:focus { border-color: #f97316; outline: none; box-shadow: 0 0 0 3px rgba(249,115,22,.12); }
.error { color: #b91c1c; font-size: 12px; }
.primary-btn { display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg,#f97316,#ea580c); color: #fff; border: none; border-radius: 10px; padding: 11px 18px; font-weight: 700; cursor: pointer; text-decoration: none; }
.action-row { margin-top: 22px; }
@media (max-width: 760px) {
  .field-grid { grid-template-columns: 1fr; }
  .form-header { flex-direction: column; align-items: flex-start; }
}
</style>
@endsection
