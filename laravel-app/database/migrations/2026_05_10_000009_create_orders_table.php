<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_phone');
            $table->text('buyer_address');
            $table->string('buyer_city')->nullable();
            $table->decimal('total_harga', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'done', 'cancelled'])->default('pending');
            $table->integer('reminder_count')->default(0);
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
