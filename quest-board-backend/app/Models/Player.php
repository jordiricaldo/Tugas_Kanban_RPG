<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'guild_id', 'name', 'class', 'avatar',
        'level', 'exp', 'gold', 'quests_cleared',
        'streak', 'best_streak',
    ];

    public function guild()
    {
        return $this->belongsTo(Guild::class);
    }

    public function quests()
    {
        return $this->hasMany(Quest::class);
    }

    // Achievements berdasarkan stats
    public function getAchievementsAttribute(): array
    {
        $achievements = [];

        if ($this->quests_cleared >= 1)
            $achievements[] = ['id' => 'first_blood', 'name' => 'First Blood', 'icon' => '⚔️', 'desc' => 'Clear quest pertama'];
        if ($this->quests_cleared >= 5)
            $achievements[] = ['id' => 'veteran', 'name' => 'Veteran', 'icon' => '🛡️', 'desc' => 'Clear 5 quest'];
        if ($this->quests_cleared >= 20)
            $achievements[] = ['id' => 'champion', 'name' => 'Champion', 'icon' => '👑', 'desc' => 'Clear 20 quest'];
        if ($this->best_streak >= 3)
            $achievements[] = ['id' => 'on_fire', 'name' => 'On Fire', 'icon' => '🔥', 'desc' => '3 streak berturut-turut'];
        if ($this->best_streak >= 7)
            $achievements[] = ['id' => 'unstoppable', 'name' => 'Unstoppable', 'icon' => '💀', 'desc' => '7 streak berturut-turut'];
        if ($this->level >= 5)
            $achievements[] = ['id' => 'elite', 'name' => 'Elite', 'icon' => '⭐', 'desc' => 'Mencapai level 5'];
        if ($this->level >= 10)
            $achievements[] = ['id' => 'legend', 'name' => 'Legend', 'icon' => '🌟', 'desc' => 'Mencapai level 10'];
        if ($this->gold >= 5000)
            $achievements[] = ['id' => 'rich', 'name' => 'Gold Hoarder', 'icon' => '💰', 'desc' => 'Kumpulkan 5000 Gold'];

        return $achievements;
    }
}