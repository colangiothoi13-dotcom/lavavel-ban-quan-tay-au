@extends('layouts.app')

@section('content')
<div class="category-page">
    
    <!-- HEADER TRANG -->
    <div class="content-header">
        <h2>Danh mục Quần Tây Âu</h2>
        <a href="{{ route('categories.create') }}" class="btn-add">+ Thêm danh mục mới</a>
    </div>

    <!-- THÔNG BÁO THÀNH CÔNG -->
    @if ($message = Session::get('success'))
        <div class="alert-success">
            {{ $message }}
        </div>
    @endif

    <!-- BẢNG DỮ LIỆU -->
    <table class="custom-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 10%;">ID</th>
                <th style="width: 55%;">Tên danh mục</th>
                <th class="text-center" style="width: 35%;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
            <tr>
                <td class="text-center"><strong>{{ $category->id }}</strong></td>
                <td><strong style="color: #2c3e50;">{{ $category->name }}</strong></td>
                <td>
                    <div class="action-buttons">
                        <a href="{{ route('categories.show', $category->id) }}" class="btn btn-show">Xem</a>
                        <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-edit">Sửa</a>
                        <form action="{{ route('categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-delete">Xóa</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center" style="color: #6c757d; padding: 20px;">
                    Chưa có danh mục nào. Hãy bấm "Thêm danh mục mới"!
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

</div>

<style>
    /* Tiêu đề & Nút thêm */
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .content-header h2 { 
        margin: 0; 
        color: #2c3e50; 
        font-size: 20px; 
    }
    .btn-add {
        background-color: #28a745;
        color: white !important;
        padding: 9px 16px;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        font-size: 14px;
        display: inline-block;
    }
    .btn-add:hover { background-color: #218838; }

    /* Thông báo */
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb;
        font-size: 14px;
    }

    /* Bảng danh mục chuẩn định dạng */
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #dee2e6;
        padding: 12px 15px;
        text-align: left;
        font-size: 14px;
        vertical-align: middle;
    }
    .custom-table th {
        background-color: #343a40;
        color: white;
        font-weight: 600;
    }
    .custom-table tr:nth-child(even) { background-color: #f8f9fa; }
    .custom-table tr:hover { background-color: #f1f3f5; }

    .text-center { text-align: center; }

    /* Các nút Thao tác */
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 8px;
        align-items: center;
    }
    .btn {
        padding: 6px 14px;
        text-decoration: none;
        border-radius: 4px;
        font-size: 13px;
        border: none;
        cursor: pointer;
        color: white !important;
        display: inline-block;
    }
    .btn-show { background-color: #17a2b8; }
    .btn-show:hover { background-color: #138496; }

    .btn-edit { background-color: #ffc107; color: #212529 !important; }
    .btn-edit:hover { background-color: #e0a800; }

    .btn-delete { background-color: #dc3545; }
    .btn-delete:hover { background-color: #c82333; }
</style>
@endsection