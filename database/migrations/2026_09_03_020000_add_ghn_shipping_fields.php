<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('ghn_province_id')->nullable()->after('city');
            $table->unsignedInteger('ghn_district_id')->nullable()->after('ghn_province_id');
            $table->string('ghn_ward_code', 20)->nullable()->after('ghn_district_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 12, 2)->default(0)->after('total');
            $table->unsignedInteger('ghn_district_id')->nullable()->after('shipping_fee');
            $table->string('ghn_ward_code', 20)->nullable()->after('ghn_district_id');
            $table->string('ghn_order_code', 50)->nullable()->unique()->after('ghn_ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', fn (Blueprint $table) => $table->dropColumn(['ghn_province_id', 'ghn_district_id', 'ghn_ward_code']));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['ghn_order_code']);
            $table->dropColumn(['shipping_fee', 'ghn_district_id', 'ghn_ward_code', 'ghn_order_code']);
        });
    }
};
