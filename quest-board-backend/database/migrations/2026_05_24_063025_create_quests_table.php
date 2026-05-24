<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('quests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('guild_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('status')->default('available'); // available, accepted, in_battle, cleared
        $table->string('rank')->default('C'); // S, A, B, C
        $table->integer('reward_exp')->default(50);
        $table->integer('reward_gold')->default(100);
        $table->string('doomsday')->nullable(); // Deadline dalam bentuk string sementara agar mudah
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
