<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->timestamp('archived_at')->nullable()->after('completed_at');
            $table->index(['status', 'completed_at', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'completed_at', 'archived_at']);
            $table->dropColumn(['completed_at', 'archived_at']);
        });
    }
};
