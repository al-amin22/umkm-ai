<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('wa_number')->unique();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('active_context')->nullable();
            $table->json('context_data')->nullable();
            $table->dateTime('last_activity');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_sessions');
    }
};
