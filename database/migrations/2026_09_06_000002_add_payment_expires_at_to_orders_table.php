<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_expires_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dateTime('payment_expires_at')->nullable()->after('payment_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_expires_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('payment_expires_at');
            });
        }
    }
};