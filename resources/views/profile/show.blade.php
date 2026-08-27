@extends('layouts.app')

@section('content')
<style>
    .admin-profile-container { padding: 0; max-width: none; }
    .page-title { font-size: 22px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
    
    .profile-card { background: transparent; border: 0; border-radius: 0; padding: 0; box-shadow: none; }
    
    /* Giao diện phần Header thông tin và Avatar */
    .profile-header { display: flex; align-items: center; gap: 20px; border-bottom: 1px solid #edf2f7; padding-bottom: 20px; margin-bottom: 24px; }
    .avatar-wrapper { position: relative; width: 72px; height: 72px; flex-shrink: 0; }
    .avatar-circle { width: 100%; height: 100%; border-radius: 50%; background: #edf2f7; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .avatar-icon { width: 40px; height: 40px; fill: #a0aec0; }
    .avatar-image { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    
    /* Gom nhóm phần thông tin bên phải avatar và nút upload */
    .user-header-content { display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .user-info-brief h3 { margin: 0; font-size: 18px; color: #2d3748; }
    .user-info-brief p { margin: 2px 0 0; font-size: 13px; color: #718096; }

    /* Style cho ô chọn file nhỏ gọn nằm cạnh avatar */
    .avatar-upload-inline { display: flex; align-items: center; gap: 10px; }
    .avatar-upload-inline input[type="file"] { font-size: 12px; color: #4a5568; }
    .avatar-help { color: #718096; font-size: 11px; margin-top: 2px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full-width { grid-column: span 2; }
    
    .form-group label { font-size: 13px; font-weight: 600; color: #4a5568; }
    .form-control { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; color: #2d3748; outline: none; box-sizing: border-box; }
    .form-control:focus { border-color: #3182ce; box-shadow: 0 0 0 1px #3182ce; }
    .form-control[disabled] { background-color: #f7fafc; color: #a0aec0; cursor: not-allowed; }

    .alert-success { background: #c6f6d5; color: #22543d; padding: 10px 14px; border-radius: 6px; font-size: 14px; margin-bottom: 16px; }
    .alert-error { background: #fed7d7; color: #742a2a; padding: 10px 14px; border-radius: 6px; font-size: 14px; margin-bottom: 16px; }

    .action-buttons { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; border-top: 1px solid #edf2f7; padding-top: 20px; }
    .action-right { display: flex; gap: 12px; }
    
    .btn-save { background: #3182ce; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.2s; }
    .btn-save:hover { background: #2b6cb0; }
    .btn-logout { background: #e2e8f0; color: #4a5568; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.2s; }
    .btn-logout:hover { background: #cbd5e0; }
</style>

<div class="admin-profile-container">
    <div class="page-title">Thông tin tài khoản</div>

    @if(session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="profile-card">
        <form method="POST" action="{{ route(auth()->user()->isAdmin() ? 'admin.profile.update' : 'user.profile.update') }}" enctype="multipart/form-data">
            @csrf 
            @method('PUT')

            <!-- PHẦN HEADER: AVATAR NẰM CẠNH TÊN, VAI TRÒ VÀ NÚT CHỌN ẢNH -->
            <div class="profile-header">
                <div class="avatar-wrapper">
                    <div class="avatar-circle">
                        @if($user->avatar)
                            <img class="avatar-image" src="{{ asset('storage/' . $user->avatar) }}" alt="Ảnh đại diện của {{ $user->name }}">
                        @else
                            <svg class="avatar-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        @endif
                    </div>
                </div>
                
                <div class="user-header-content">
                    <div class="user-info-brief">
                        <h3>{{ $user->name }}</h3>
                        <p>Vai trò: <strong>{{ strtoupper($user->role ?? 'User') }}</strong></p>
                    </div>
                    
                    <!-- Ô chọn file được đưa lên đây ngay cạnh thông tin -->
                    <div class="avatar-upload-inline">
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Họ và Tên</label>
                    <input class="form-control" name="name" value="{{ old('name', $user->name) }}" required>
                </div>

                <div class="form-group">
                    <label>Địa chỉ Email (Không đổi)</label>
                    <input class="form-control" value="{{ $user->email }}" disabled>
                </div>

                <div class="form-group">
                    <label>Giới tính</label>
                    <select class="form-control" name="gender">
                        <option value="">-- Chọn giới tính --</option>
                        @foreach(['Nam','Nữ','Khác'] as $gender)
                            <option value="{{ $gender }}" @selected(old('gender', $user->gender) === $gender)>{{ $gender }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Ngày sinh</label>
                    <input type="date" class="form-control" name="birth_date" value="{{ old('birth_date', optional($user->birth_date)->format('Y-m-d')) }}">
                </div>

                <div class="form-group full-width">
                    <label>Số điện thoại</label>
                    <input class="form-control" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Nhập số điện thoại">
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" form="logout-form" class="btn-logout">Đăng xuất</button>
                
                <div class="action-right">
                    <button type="submit" class="btn-save">Cập nhật thông tin</button>
                </div>
            </div>
        </form>

        <form id="logout-form" method="POST" action="{{ route(auth()->user()->isAdmin() ? 'admin.logout' : 'buyer.logout') }}" style="display: none;">
            @csrf
        </form>
    </div>
</div>
@endsection
