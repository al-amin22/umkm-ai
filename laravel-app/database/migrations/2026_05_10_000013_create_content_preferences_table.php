<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->enum('gaya_bahasa', ['formal', 'santai', 'gaul'])->default('santai');
            $table->enum('emoji_preference', ['sering', 'jarang', 'tidak'])->default('sering');
            $table->enum('panjang_konten', ['pendek', 'sedang', 'panjang'])->default('sedang');
            $table->text('contoh_disukai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_preferences');
    }
};
