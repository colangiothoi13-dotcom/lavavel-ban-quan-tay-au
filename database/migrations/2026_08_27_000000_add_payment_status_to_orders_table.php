<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->after('payment_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('payment_status');
            });
        }
    }
};
