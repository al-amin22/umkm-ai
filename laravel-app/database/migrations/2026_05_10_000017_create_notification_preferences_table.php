<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->boolean('jeda_aktif')->default(false);
            $table->dateTime('jeda_sampai')->nullable();
            $table->integer('consecutive_ignored')->default(0);
            $table->enum('frekuensi_mode', ['normal', 'reduced', 'minimal'])->default('normal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
