<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->enum('status', ['active', 'grace', 'expired'])->default('active');
            $table->enum('plan', ['trial', 'starter', 'growth'])->default('trial');
            $table->dateTime('mulai_at');
            $table->dateTime('expired_at');
            $table->dateTime('grace_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
