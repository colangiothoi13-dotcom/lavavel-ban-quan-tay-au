<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('momo_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('momo_order_id')->unique();
            $table->string('request_id')->index();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending')->index();
            $table->string('transaction_id')->nullable();
            $table->unsignedBigInteger('response_time')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_payment_attempts');
    }
};
