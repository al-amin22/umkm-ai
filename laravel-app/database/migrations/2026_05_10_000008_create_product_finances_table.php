<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->decimal('bahan_baku', 10, 2)->default(0);
            $table->decimal('kemasan', 10, 2)->default(0);
            $table->decimal('tenaga_kerja', 10, 2)->default(0);
            $table->decimal('biaya_lain', 10, 2)->default(0);
            $table->decimal('hpp_total', 10, 2)->default(0);
            $table->decimal('harga_jual', 10, 2)->default(0);
            $table->decimal('margin_persen', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_finances');
    }
};
