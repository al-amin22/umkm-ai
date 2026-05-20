<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('wa_number_owner');
            $table->string('wa_number_helper')->nullable();
            $table->string('nama_toko');
            $table->string('slug')->unique();
            $table->string('jenis_produk');
            $table->string('nama_owner')->nullable();
            $table->text('alamat')->nullable();
            $table->string('jam_buka')->nullable();
            $table->string('jam_tutup')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->dateTime('buka_lagi_at')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('nama_bank')->nullable();
            $table->string('nama_pemilik_rekening')->nullable();
            $table->string('wa_nomor_darurat')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
