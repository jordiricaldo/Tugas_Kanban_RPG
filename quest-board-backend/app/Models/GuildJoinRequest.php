<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuildJoinRequest extends Model
{
    protected $fillable = ['guild_id', 'player_id', 'status'];

    public function guild()
    {
        return $this->belongsTo(Guild::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}