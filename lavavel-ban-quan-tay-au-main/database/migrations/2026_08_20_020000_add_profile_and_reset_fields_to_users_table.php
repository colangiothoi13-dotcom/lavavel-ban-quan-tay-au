<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('name');
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('phone', 30)->nullable()->after('birth_date');
            $table->string('reset_otp', 6)->nullable()->after('remember_token');
            $table->timestamp('reset_otp_expires_at')->nullable()->after('reset_otp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'birth_date', 'phone', 'reset_otp', 'reset_otp_expires_at',
            ]);
        });
    }
};
