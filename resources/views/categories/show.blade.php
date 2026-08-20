@extends('layouts.app')

@section('content')
<style>
    /* Chỉ giữ lại style cho các thành phần bên trong */
    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .category-header h2 { 
        margin: 0; 
        color: #2c3e50; 
        font-size: 22px; 
    }
    .info-card {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 20px;
    }
    .info-item {
        margin-bottom: 12px;
        font-size: 15px;
    }
    .info-item:last-child { 
        margin-bottom: 0; 
    }
    .info-label { 
        font-weight: bold; 
        color: #495057; 
        width: 140px; 
        display: inline-block; 
    }
    .info-value { 
        color: #0d6efd; 
        font-weight: bold; 
    }
    .btn-back {
        padding: 8px 16px;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        display: inline-block;
        background-color: #6c757d;
        color: white;
        transition: 0.2s;
        font-size: 14px;
    }
    .btn-back:hover { 
        background-color: #5c636a; 
    }
</style>

<div class="category-header">
    <h2>Chi Tiết Danh Mục</h2>
    <a href="{{ route('categories.index') }}" class="btn-back">Quay lại</a>
</div>

<div class="info-card">
    <div class="info-item">
        <span class="info-label">Mã ID danh mục:</span>
        <span>{{ $category->id }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">Tên danh mục:</span>
        <span class="info-value">{{ $category->name }}</span>
    </div>
    <div class="info-item">
        <span class="info-label">Ngày tạo:</span>
        <span>{{ $category->created_at ? $category->created_at->format('d/m/Y H:i') : 'Chưa cập nhật' }}</span>
    </div>
</div>
@endsection