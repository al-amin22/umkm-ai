<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('nama_workflow');           // e.g. "morning_briefing", "reminder_pesanan"
            $table->enum('status', ['success', 'failed', 'skipped'])->default('success');
            $table->text('pesan')->nullable();         // summary atau error message
            $table->integer('durasi_ms')->nullable();  // execution time
            $table->json('konteks')->nullable();       // additional context data
            $table->timestamp('dijalankan_at');
            $table->timestamps();

            $table->index(['shop_id', 'dijalankan_at'], 'wf_logs_shop_time_idx');
            $table->index('nama_workflow', 'wf_logs_nama_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
    }
};
