<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Xóa bảng cũ nếu đã tồn tại để làm mới
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');

        // Bảng chứa thông tin chung của sản phẩm (Quần Tây Âu)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2); // Giá cơ bản
            $table->timestamps();
        });

        // Bảng chứa thông tin từng biến thể (Màu sắc, Size, Số lượng, Ảnh, Giá riêng)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('color'); // Ví dụ: Đen, Ghi, Xám, Kem
            $table->string('size');  // Ví dụ: 29, 30, 31, 32, S, M, L, XL
            $table->integer('stock')->default(0); // Số lượng tồn kho
            $table->decimal('price', 12, 2)->nullable(); // Giá riêng cho biến thể (nếu có)
            $table->string('image')->nullable(); // Lưu đường dẫn file upload trong thư mục storage
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
