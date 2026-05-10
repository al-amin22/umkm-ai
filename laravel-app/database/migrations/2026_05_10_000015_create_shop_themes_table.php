<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->integer('template_id')->default(1);
            $table->string('warna_utama')->default('#3B82F6');
            $table->string('warna_sekunder')->default('#1E40AF');
            $table->string('banner_url')->nullable();
            $table->string('banner_public_id')->nullable();
            $table->dateTime('last_updated')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_themes');
    }
};
