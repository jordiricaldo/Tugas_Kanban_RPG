<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guild extends Model
{
    protected $fillable = [
        'name',
        'level',
        'exp',
        'gold',
    ];

    public function quests()
    {
        return $this->hasMany(Quest::class);
    }
}