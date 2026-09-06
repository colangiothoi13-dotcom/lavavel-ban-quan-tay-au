<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'stock_return_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('stock_return_status')->default('held')->after('cancellation_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'stock_return_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('stock_return_status');
            });
        }
    }
};
