@extends('layouts.app')

@section('content')
<style>
    .category-create-box {
        max-width: 600px;
        margin: 0 auto;
        background: #ffffff;
        padding: 25px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .category-create-box .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
    .category-create-box .header h2 { margin: 0; color: #2c3e50; }
    .category-create-box .form-group { margin-bottom: 20px; }
    .category-create-box .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
    .category-create-box .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 15px;
        }
    .category-create-box .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
    .category-create-box .btn-group {
            display: flex;
            gap: 10px;
        }
    .category-create-box .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
    .category-create-box .btn-submit { background-color: #0d6efd; color: white; }
    .category-create-box .btn-submit:hover { background-color: #0b5ed7; }
    .category-create-box .btn-back { background-color: #6c757d; color: white; }
    .category-create-box .btn-back:hover { background-color: #5c636a; }
</style>

<div class="category-create-box">
    <div class="header">
        <h2>Thêm Danh Mục Mới</h2>
        <a href="{{ route('categories.index') }}" class="btn btn-back">Quay lại</a>
    </div>

    <form action="{{ route('categories.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">Tên danh mục quần tây:</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="Ví dụ: Quần tây công sở, Quần tây Gurkha..." required>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-submit">Lưu thông tin</button>
        </div>
    </form>
</div>
@endsection