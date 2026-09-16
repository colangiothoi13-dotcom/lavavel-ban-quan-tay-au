<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'momo_request_id')) {
                $table->string('momo_request_id')->nullable()->after('momo_order_id');
            }
            if (! Schema::hasColumn('orders', 'momo_transaction_id')) {
                $table->string('momo_transaction_id')->nullable()->after('momo_request_id');
            }
            if (! Schema::hasColumn('orders', 'momo_response_time')) {
                $table->unsignedBigInteger('momo_response_time')->nullable()->after('momo_transaction_id');
            }
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('orders', 'momo_request_id') ? 'momo_request_id' : null,
            Schema::hasColumn('orders', 'momo_transaction_id') ? 'momo_transaction_id' : null,
            Schema::hasColumn('orders', 'momo_response_time') ? 'momo_response_time' : null,
        ]);
        if ($columns !== []) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};