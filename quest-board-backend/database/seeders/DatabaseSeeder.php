<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Guild;
use App\Models\Quest;
use App\Models\Player;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $guild = Guild::create([
            'name'  => 'Shadow Hunters Guild',
            'level' => 12,
            'exp'   => 4500,
            'gold'  => 24500,
        ]);

        // Players
        $players = [
            Player::create(['guild_id' => $guild->id, 'name' => 'Zephyr',  'class' => 'Rogue',   'avatar' => 'rogue',   'level' => 8,  'exp' => 200, 'gold' => 3200, 'quests_cleared' => 14, 'streak' => 2, 'best_streak' => 5]),
            Player::create(['guild_id' => $guild->id, 'name' => 'Aelindra','class' => 'Mage',    'avatar' => 'mage',    'level' => 6,  'exp' => 100, 'gold' => 2100, 'quests_cleared' => 9,  'streak' => 0, 'best_streak' => 3]),
            Player::create(['guild_id' => $guild->id, 'name' => 'Borrak',  'class' => 'Warrior', 'avatar' => 'warrior', 'level' => 10, 'exp' => 400, 'gold' => 5800, 'quests_cleared' => 21, 'streak' => 7, 'best_streak' => 7]),
            Player::create(['guild_id' => $guild->id, 'name' => 'Sylvara', 'class' => 'Archer',  'avatar' => 'archer',  'level' => 4,  'exp' => 50,  'gold' => 900,  'quests_cleared' => 3,  'streak' => 0, 'best_streak' => 2]),
        ];

        [$zephyr, $aelindra, $borrak, $sylvara] = $players;

        // Quests
        Quest::create(['guild_id' => $guild->id, 'player_id' => null,          'title' => 'Fix Core Auth Middleware',      'description' => 'Sistem otentikasi jebol terkena serangan bypass token.',          'status' => 'available',  'rank' => 'S', 'reward_exp' => 800, 'reward_gold' => 1500, 'doomsday' => '1 Day Left']);
        Quest::create(['guild_id' => $guild->id, 'player_id' => null,          'title' => 'Refactor Database Queries',     'description' => 'Query N+1 menyebabkan server lambat saat load tinggi.',           'status' => 'available',  'rank' => 'B', 'reward_exp' => 300, 'reward_gold' => 500,  'doomsday' => '5 Days Left']);
        Quest::create(['guild_id' => $guild->id, 'player_id' => $aelindra->id, 'title' => 'Design Landing Page UI',        'description' => 'Buat desain antarmuka depan yang memukau untuk menarik pengguna.', 'status' => 'accepted',   'rank' => 'A', 'reward_exp' => 450, 'reward_gold' => 750,  'doomsday' => '3 Days Left']);
        Quest::create(['guild_id' => $guild->id, 'player_id' => $zephyr->id,   'title' => 'Write Unit Tests for API',      'description' => 'Coverage saat ini 12%. Target minimal 80% sebelum deploy.',        'status' => 'in_battle',  'rank' => 'B', 'reward_exp' => 350, 'reward_gold' => 600,  'doomsday' => '2 Days Left']);
        Quest::create(['guild_id' => $guild->id, 'player_id' => $borrak->id,   'title' => 'Integrate Payment Gateway',     'description' => 'Hubungkan Midtrans ke sistem checkout.',                          'status' => 'in_battle',  'rank' => 'A', 'reward_exp' => 500, 'reward_gold' => 900,  'doomsday' => '4 Days Left']);
        Quest::create(['guild_id' => $guild->id, 'player_id' => $borrak->id,   'title' => 'Setup CI/CD Pipeline',          'description' => 'GitHub Actions berhasil dikonfigurasi untuk auto-deploy.',         'status' => 'cleared',    'rank' => 'C', 'reward_exp' => 200, 'reward_gold' => 300,  'doomsday' => null]);
        Quest::create(['guild_id' => $guild->id, 'player_id' => $sylvara->id,  'title' => 'Write API Documentation',       'description' => 'Dokumentasi endpoint lengkap menggunakan Swagger.',               'status' => 'accepted',   'rank' => 'C', 'reward_exp' => 150, 'reward_gold' => 250,  'doomsday' => '7 Days Left']);
    }
}