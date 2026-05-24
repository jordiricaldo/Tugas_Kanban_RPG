<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('class')->default('Warrior'); // Warrior, Mage, Rogue, Archer
            $table->string('avatar')->default('warrior'); // untuk pixel art
            $table->integer('level')->default(1);
            $table->integer('exp')->default(0);
            $table->integer('gold')->default(0);
            $table->integer('quests_cleared')->default(0);
            $table->integer('streak')->default(0);        // quest clear berturut-turut
            $table->integer('best_streak')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};