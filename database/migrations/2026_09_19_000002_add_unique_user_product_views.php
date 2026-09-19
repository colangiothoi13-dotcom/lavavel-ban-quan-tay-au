<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the newest view for each user/product pair; on timestamp ties,
        // the largest id is the deterministic winner.
        DB::table('user_product_views as older')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('user_product_views as newer')
                    ->whereColumn('newer.user_id', 'older.user_id')
                    ->whereColumn('newer.product_id', 'older.product_id')
                    ->where(function ($query) {
                        $query->whereColumn('newer.updated_at', '>', 'older.updated_at')
                            ->orWhere(function ($query) {
                                $query->whereColumn('newer.updated_at', 'older.updated_at')
                                    ->whereColumn('newer.id', '>', 'older.id');
                            });
                    });
            })
            ->delete();

        Schema::table('user_product_views', function (Blueprint $table) {
            $table->unique(['user_id', 'product_id'], 'user_product_views_user_id_product_id_unique');
            $table->index(['user_id', 'updated_at'], 'user_product_views_user_id_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_product_views', function (Blueprint $table) {
            $table->dropIndex('user_product_views_user_id_updated_at_index');
            $table->dropUnique('user_product_views_user_id_product_id_unique');
        });
    }
};
