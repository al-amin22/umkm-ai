<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('rfm_segment', 20)->default('Baru')->after('last_order_at');
            $table->tinyInteger('rfm_r')->default(0)->after('rfm_segment'); // Recency score 1-5
            $table->tinyInteger('rfm_f')->default(0)->after('rfm_r');       // Frequency score 1-5
            $table->tinyInteger('rfm_m')->default(0)->after('rfm_f');       // Monetary score 1-5
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['rfm_segment', 'rfm_r', 'rfm_f', 'rfm_m']);
        });
    }
};
