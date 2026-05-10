<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('wa_number')->unique();
            $table->string('step_terakhir')->default('mulai');
            $table->json('data_terkumpul');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_sessions');
    }
};
