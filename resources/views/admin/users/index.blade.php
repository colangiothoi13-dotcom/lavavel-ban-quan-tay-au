@extends('layouts.app')

@section('content')
<div class="admin-user-page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Quản trị hệ thống</p>
            <h1>Quản lý người dùng</h1>
        </div>
        <a href="{{ route('admin.users.create') }}" class="primary-btn">+ Thêm người dùng</a>
    </div>

    @if(session('status'))
        <div class="notice success">{{ session('status') }}</div>
    @endif

    <form class="filter-box" method="GET" action="{{ route('admin.users.index') }}">
        <div class="field-group">
            <label for="keyword">Tìm người dùng</label>
            <input id="keyword" name="keyword" value="{{ request('keyword') }}" placeholder="Tên, email, SĐT...">
        </div>
        <div class="field-group">
            <label for="role">Vai trò</label>
            <select id="role" name="role">
                <option value="">Tất cả</option>
                <option value="admin" @selected(request('role') === 'admin')>Admin</option>
                <option value="user" @selected(request('role') === 'user')>Khách hàng</option>
            </select>
        </div>
        <button type="submit" class="secondary-btn">Lọc</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Số điện thoại</th>
                    <th>Giới tính</th>
                    <th>Vai trò</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone ?? '—' }}</td>
                        <td>
                            @switch($user->gender)
                                @case('male') Nam @break
                                @case('female') Nữ @break
                                @case('other') Khác @break
                                @default —
                            @endswitch
                        </td>
                        <td>
                            <span class="role-badge role-{{ $user->role }}">
                                {{ $user->role === 'admin' ? 'Admin' : 'Khách hàng' }}
                            </span>
                        </td>
                        <td class="actions-col">
                            <a href="{{ route('admin.users.edit', $user) }}" class="mini-btn edit">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-cell">Không có người dùng nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        {{ $users->links() }}
    </div>
</div>

<style>
.admin-user-page { color: #1f2937; }
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.eyebrow { margin: 0 0 6px; color: #f4511e; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; }
.page-header h1 { margin: 0; font-size: 29px; }
.primary-btn, .secondary-btn, .mini-btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; text-decoration: none; }
.primary-btn { background: linear-gradient(135deg,#f97316,#ea580c); color: #fff; padding: 11px 16px; }
.secondary-btn { background: #1f2937; color: white; padding: 10px 18px; }
.notice { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
.notice.success { background: #dcfce7; color: #166534; }
.filter-box { display: flex; flex-wrap: wrap; align-items: end; gap: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 18px; }
.field-group { display: flex; flex-direction: column; gap: 6px; min-width: 220px; }
.field-group label { font-size: 12px; font-weight: 700; color: #475569; }
.field-group input, .field-group select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
.table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 14px; border-bottom: 1px solid #edf2f7; text-align: left; }
th { background: #f8fafc; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: #475569; }
tbody tr:hover { background: #f8fafc; }
.role-badge { display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.role-admin { background: #dbeafe; color: #1d4ed8; }
.role-user { background: #dcfce7; color: #166534; }
.actions-col { width: 100px; }
.mini-btn.edit { background: #fef3c7; color: #92400e; padding: 7px 12px; }
.empty-cell { text-align: center; color: #64748b; padding: 28px 12px; }
.pagination-wrap { margin-top: 14px; }
.pagination-wrap nav { display: flex; justify-content: flex-end; }
.pagination-wrap .pagination { display: flex; gap: 8px; list-style: none; }
.pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 10px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #334155; text-decoration: none; }
.pagination .page-item.active span { background: #f4511e; border-color: #f4511e; color: #fff; }
@media (max-width: 740px) {
  .page-header { flex-direction: column; align-items: flex-start; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 760px; }
}
</style>
@endsection
