<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('nama_produk');
            $table->string('slug');
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 10, 2);
            $table->enum('status', ['active', 'draft', 'inactive'])->default('active');
            $table->string('foto_url')->nullable();
            $table->string('foto_public_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
