<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Guild extends Model
{
    protected $fillable = [
        'name', 'level', 'exp', 'gold',
        'guild_master_id', 'invite_code', 'description',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($guild) {
            if (empty($guild->invite_code)) {
                $guild->invite_code = strtoupper(Str::random(6));
            }
        });
    }

    public function quests()
    {
        return $this->hasMany(Quest::class);
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function guildMaster()
    {
        return $this->belongsTo(User::class, 'guild_master_id');
    }
}