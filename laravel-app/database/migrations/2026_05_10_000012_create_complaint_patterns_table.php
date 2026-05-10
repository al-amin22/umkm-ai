<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('tipe_komplain');
            $table->integer('jumlah')->default(1);
            $table->string('periode');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_patterns');
    }
};
