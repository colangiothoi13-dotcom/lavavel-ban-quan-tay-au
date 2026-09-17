<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        $descriptions = [
            'Regular Fit (Ống đứng):' => 'Kiểu quần ống đứng cổ điển, thoải mái và phù hợp để mặc hằng ngày hoặc đi làm.',
            'Slim Fit (Ống côn)' => 'Kiểu quần ôm gọn vừa phải, tạo vẻ thanh lịch và hiện đại cho người mặc.',
            'Baggy / Ống rộng:' => 'Kiểu quần ống rộng phóng khoáng, thoải mái và dễ phối với nhiều phong cách.',
            'Cạp Sidetab (Sartorial):' => 'Kiểu quần may đo với đai điều chỉnh bên hông, mang phong cách sartorial tinh tế.',
        ];

        foreach ($descriptions as $name => $description) {
            \Illuminate\Support\Facades\DB::table('categories')
                ->where('name', $name)
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};