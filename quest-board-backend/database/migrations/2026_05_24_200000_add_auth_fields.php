<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add guild_master_id to guilds
        Schema::table('guilds', function (Blueprint $table) {
            $table->foreignId('guild_master_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invite_code')->nullable()->unique();
            $table->text('description')->nullable();
        });

        // Add user_id to players (link player to auth user)
        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->default('member'); // 'guild_master' | 'member'
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'role']);
        });
        Schema::table('guilds', function (Blueprint $table) {
            $table->dropForeign(['guild_master_id']);
            $table->dropColumn(['guild_master_id', 'invite_code', 'description']);
        });
    }
};