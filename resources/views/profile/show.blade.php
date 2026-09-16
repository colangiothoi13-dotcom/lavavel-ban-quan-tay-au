@extends('layouts.app')

@section('content')
<style>
    .profile-page { color: #2f2f2f; }
    .profile-heading { padding-bottom: 24px; border-bottom: 1px solid #e8e8e8; }
    .profile-heading h1 { margin: 0 0 7px; font-size: 27px; line-height: 1.25; font-weight: 500; }
    .profile-heading p { margin: 0; color: #555; font-size: 16px; }
    .profile-alert { margin-top: 18px; padding: 11px 14px; border-radius: 4px; font-size: 14px; }
    .profile-alert.success { color: #22543d; background: #e6ffed; border: 1px solid #b7ebc6; }
    .profile-alert.error { color: #842029; background: #fff0f0; border: 1px solid #f5c2c7; }
    .profile-form-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 64px; padding: 44px 30px 10px; }
    .profile-fields { min-width: 0; }
    .profile-field { display: grid; grid-template-columns: 170px minmax(0, 1fr); align-items: center; min-height: 70px; }
    .profile-field label { padding-right: 28px; color: #727272; font-size: 16px; text-align: right; }
    .profile-control { width: 100%; height: 48px; padding: 0 14px; border: 1px solid #d7d7d7; border-radius: 3px; background: #fff; color: #222; font: inherit; font-size: 16px; outline: none; transition: border-color .2s, box-shadow .2s; }
    .profile-control:focus { border-color: #777; box-shadow: 0 0 0 2px rgba(0, 0, 0, .06); }
    .profile-control[disabled] { color: #555; background: #fafafa; cursor: not-allowed; }
    .profile-hint { grid-column: 2; margin: -7px 0 10px; color: #999; font-size: 13px; }
    .profile-actions { margin: 24px 0 0 170px; }
    .profile-save { min-width: 104px; padding: 13px 24px; border: 0; border-radius: 3px; background: #f04b2f; color: #fff; font-size: 16px; cursor: pointer; transition: background .2s; }
    .profile-save:hover { background: #d93f26; }
    .profile-avatar-panel { align-self: start; min-height: 315px; padding-left: 54px; border-left: 1px solid #ededed; text-align: center; }
    .profile-avatar { width: 150px; height: 150px; margin: 30px auto; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-avatar svg { width: 82px; height: 82px; fill: none; stroke: #c7c7c7; stroke-width: 1.35; }
    .profile-file { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
    .profile-file-button { display: inline-block; padding: 11px 25px; border: 1px solid #ddd; border-radius: 3px; background: #fff; color: #444; font-size: 15px; cursor: pointer; transition: background .2s; }
    .profile-file-button:hover { background: #f7f7f7; }
    .profile-avatar-help { margin-top: 18px; color: #999; font-size: 13px; line-height: 1.65; }
    @media (max-width: 900px) {
        .profile-form-layout { grid-template-columns: 1fr; gap: 28px; padding: 30px 0 10px; }
        .profile-avatar-panel { grid-row: 1; min-height: auto; padding: 0 0 28px; border-left: 0; border-bottom: 1px solid #ededed; }
        .profile-avatar { margin-top: 0; }
    }
    @media (max-width: 600px) {
        .profile-heading h1 { font-size: 23px; }
        .profile-field { grid-template-columns: 1fr; gap: 7px; margin-bottom: 17px; }
        .profile-field label { padding: 0; text-align: left; }
        .profile-hint { grid-column: 1; margin: 0; }
        .profile-actions { margin-left: 0; }
        .profile-save { width: 100%; }
    }
</style>

<div class="profile-page">
    <div class="profile-heading">
        <h1>Hồ Sơ Của Tôi</h1>
        <p>Quản lý thông tin hồ sơ để bảo mật tài khoản</p>
    </div>

    @if(session('status'))
        <div class="profile-alert success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="profile-alert error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route(auth()->user()->isAdmin() ? 'admin.profile.update' : 'user.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="profile-form-layout">
            <div class="profile-fields">
                <div class="profile-field">
                    <label for="profile-name">Họ và tên</label>
                    <input id="profile-name" class="profile-control" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="profile-field">
                    <label for="profile-email">Email</label>
                    <input id="profile-email" class="profile-control" value="{{ $user->email }}" disabled>
                    <div class="profile-hint">Email đăng nhập không thể thay đổi.</div>
                </div>
                <div class="profile-field">
                    <label for="profile-phone">Số điện thoại</label>
                    <input id="profile-phone" class="profile-control" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Nhập số điện thoại">
                </div>
                <div class="profile-field">
                    <label for="profile-gender">Giới tính</label>
                    <select id="profile-gender" class="profile-control" name="gender">
                        <option value="">-- Chọn giới tính --</option>
                        @foreach(['Nam', 'Nữ', 'Khác'] as $gender)
                            <option value="{{ $gender }}" @selected(old('gender', $user->gender) === $gender)>{{ $gender }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="profile-field">
                    <label for="profile-birth-date">Ngày sinh</label>
                    <input id="profile-birth-date" type="date" class="profile-control" name="birth_date" value="{{ old('birth_date', optional($user->birth_date)->format('Y-m-d')) }}">
                </div>
                <div class="profile-actions">
                    <button type="submit" class="profile-save">Lưu</button>
                </div>
            </div>

            <aside class="profile-avatar-panel">
                <div class="profile-avatar" id="profile-avatar-preview">
                    @if($user->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="Ảnh đại diện của {{ $user->name }}">
                    @else
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <circle cx="32" cy="23" r="11"></circle>
                            <path d="M16 51c0-10 7.2-17 16-17s16 7 16 17" stroke-linecap="round"></path>
                        </svg>
                    @endif
                </div>
                <input id="profile-avatar-input" class="profile-file" type="file" name="avatar" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                <label class="profile-file-button" for="profile-avatar-input">Chọn Ảnh</label>
                <div class="profile-avatar-help">Dung lượng file tối đa 2 MB<br>Định dạng: JPEG, PNG, JPG, GIF, WEBP</div>
            </aside>
        </div>
    </form>
</div>

<script>
    document.getElementById('profile-avatar-input')?.addEventListener('change', function (event) {
        const file = event.target.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const preview = document.getElementById('profile-avatar-preview');
        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = 'Ảnh đại diện đã chọn';
        image.onload = function () { URL.revokeObjectURL(image.src); };
        preview.replaceChildren(image);
    });
</script>
@endsection
