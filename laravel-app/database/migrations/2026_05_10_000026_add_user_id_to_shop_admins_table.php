<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_admins', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('nama')->nullable()->after('role');
            $table->string('email')->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('shop_admins', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class);
            $table->dropColumn(['user_id', 'nama', 'email']);
        });
    }
};
