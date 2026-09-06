<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('payment_reference')->nullable()->after('payment_method');
                $table->string('momo_order_id')->nullable()->after('payment_reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('payment_reference');
            });
        }

        if (Schema::hasColumn('orders', 'momo_order_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('momo_order_id');
            });
        }
    }
};

