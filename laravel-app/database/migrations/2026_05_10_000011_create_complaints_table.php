<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('buyer_name');
            $table->text('pesan_asli');
            $table->text('pesan_ringkasan')->nullable();
            $table->text('draft_balasan')->nullable();
            $table->enum('tipe', ['rusak', 'telat', 'salah_item', 'kualitas', 'lainnya']);
            $table->enum('urgensi', ['tinggi', 'sedang', 'rendah']);
            $table->enum('status', ['baru', 'diteruskan', 'dibalas', 'selesai'])->default('baru');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
