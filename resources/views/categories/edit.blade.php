@extends('layouts.app')

@section('content')
<style>
    .category-edit-box {
        max-width: 600px;
        margin: 0 auto;
    }
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
    .form-group { 
        margin-bottom: 20px; 
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #333;
    }
    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 15px;
    }
    .form-control:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .btn-group {
        display: flex;
        gap: 10px;
    }
    .btn {
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        border: none;
        cursor: pointer;
        display: inline-block;
        font-size: 14px;
    }
    .btn-update { 
        background-color: #ffc107; 
        color: #212529; 
        transition: 0.2s;
    }
    .btn-update:hover { 
        background-color: #e0a800; 
    }
    .btn-back { 
        background-color: #6c757d; 
        color: white; 
        transition: 0.2s;
    }
    .btn-back:hover { 
        background-color: #5c636a; 
        color: white; 
    }
</style>

<div class="category-edit-box">
    <div class="category-header">
        <h2>Chỉnh Sửa Danh Mục</h2>
        <a href="{{ route('categories.index') }}" class="btn btn-back">Quay lại</a>
    </div>

    <form action="{{ route('categories.update', $category->id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="info-card" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="name">Tên danh mục quần tây:</label>
                <input type="text" name="name" id="name" value="{{ $category->name }}" class="form-control" required>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-update">Cập nhật danh mục</button>
        </div>
    </form>
</div>
@endsection