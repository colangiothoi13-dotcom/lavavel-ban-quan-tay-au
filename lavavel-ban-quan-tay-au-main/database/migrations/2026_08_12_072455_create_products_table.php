<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên quần âu (VD: Quần âu Slimfit đen)
            $table->decimal('price', 12, 2); // Giá tiền
            $table->integer('stock')->default(0); // Số lượng tồn kho
            $table->text('description')->nullable(); // Mô tả
            $table->string('image')->nullable(); // Ảnh sản phẩm
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade'); // Khóa ngoại liên kết với danh mục
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
