<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('nama_produk');
        });

        // Index untuk filter kategori per toko
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'kategori'], 'products_shop_kategori_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_shop_kategori_idx');
            $table->dropColumn('kategori');
        });
    }
};
