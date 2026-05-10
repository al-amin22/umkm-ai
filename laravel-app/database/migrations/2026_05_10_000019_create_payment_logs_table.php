<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->enum('tipe', ['langganan', 'order']);
            $table->string('reference_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->json('webhook_payload')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
