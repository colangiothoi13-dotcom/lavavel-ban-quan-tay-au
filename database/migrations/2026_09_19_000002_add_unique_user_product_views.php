<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Process each pair separately so MySQL never deletes from a table
        // while selecting from it in a subquery. Non-NULL timestamps rank
        // above NULL; within either group, the latest timestamp / largest id wins.
        DB::table('user_product_views')
            ->select('user_id', 'product_id')
            ->distinct()
            ->get()
            ->each(function ($pair) {
                $winner = DB::table('user_product_views')
                    ->where('user_id', $pair->user_id)
                    ->where('product_id', $pair->product_id)
                    ->orderByRaw('CASE WHEN updated_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->value('id');

                DB::table('user_product_views')
                    ->where('user_id', $pair->user_id)
                    ->where('product_id', $pair->product_id)
                    ->where('id', '<>', $winner)
                    ->delete();
            });

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
