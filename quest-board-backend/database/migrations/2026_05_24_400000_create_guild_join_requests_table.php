<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guild_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['guild_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_join_requests');
    }
};