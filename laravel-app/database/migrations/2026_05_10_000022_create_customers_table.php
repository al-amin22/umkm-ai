<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('nama');
            $table->string('nomor_hp');
            $table->text('alamat')->nullable();
            $table->string('kota')->nullable();
            $table->integer('total_pesanan')->default(0);
            $table->decimal('total_belanja', 12, 2)->default(0);
            $table->dateTime('last_order_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'nomor_hp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
