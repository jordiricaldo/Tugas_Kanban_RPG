<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Guild;
use App\Models\Quest;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat 1 Guild Utama
        $guild = Guild::create([
            'name' => 'Shadow Hunters Guild',
            'level' => 12,
            'exp' => 4500,
            'gold' => 24500
        ]);

        // Membuat Quest 1 (Available)
        Quest::create([
            'guild_id' => $guild->id,
            'title' => 'Fix Core Auth Middleware',
            'description' => 'Sistem otentikasi jebol terkena serangan bypass token.',
            'status' => 'available',
            'rank' => 'S',
            'reward_exp' => 800,
            'reward_gold' => 1500,
            'doomsday' => '1 Day Left'
        ]);

        // Membuat Quest 2 (Accepted)
        Quest::create([
            'guild_id' => $guild->id,
            'title' => 'Design Landing Page UI',
            'description' => 'Buat desain antarmuka depan yang memukau.',
            'status' => 'accepted',
            'rank' => 'A',
            'reward_exp' => 450,
            'reward_gold' => 750,
            'doomsday' => '3 Days Left'
        ]);
    }
}