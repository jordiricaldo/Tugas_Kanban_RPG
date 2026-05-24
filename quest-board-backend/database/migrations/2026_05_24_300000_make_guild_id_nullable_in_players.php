<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Drop foreign key constraint dulu, lalu ubah jadi nullable
            $table->dropForeign(['guild_id']);
            $table->unsignedBigInteger('guild_id')->nullable()->change();
            $table->foreign('guild_id')->references('id')->on('guilds')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['guild_id']);
            $table->unsignedBigInteger('guild_id')->nullable(false)->change();
            $table->foreign('guild_id')->references('id')->on('guilds')->onDelete('cascade');
        });
    }
};