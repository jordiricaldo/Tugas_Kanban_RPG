<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
    protected $fillable = [
        'guild_id', 'player_id', 'title', 'description',
        'status', 'rank', 'reward_exp', 'reward_gold', 'doomsday',
    ];

    public function guild()
    {
        return $this->belongsTo(Guild::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}