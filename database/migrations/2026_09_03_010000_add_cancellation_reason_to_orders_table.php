<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'cancellation_reason')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('cancellation_reason', 500)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'cancellation_reason')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('cancellation_reason');
            });
        }
    }
};
