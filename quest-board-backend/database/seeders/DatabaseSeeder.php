<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Guild;
use App\Models\Quest;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Guild Utama ──────────────────────────────────────
        $guild = Guild::create([
            'name'  => 'Shadow Hunters Guild',
            'level' => 12,
            'exp'   => 4500,
            'gold'  => 24500,
        ]);

        // ── Available Quests ─────────────────────────────────
        Quest::create([
            'guild_id'    => $guild->id,
            'title'       => 'Fix Core Auth Middleware',
            'description' => 'Sistem otentikasi jebol terkena serangan bypass token. Segera diperbaiki sebelum data pengguna bocor.',
            'status'      => 'available',
            'rank'        => 'S',
            'reward_exp'  => 800,
            'reward_gold' => 1500,
            'doomsday'    => '1 Day Left',
        ]);

        Quest::create([
            'guild_id'    => $guild->id,
            'title'       => 'Refactor Database Queries',
            'description' => 'Query N+1 menyebabkan server lambat saat load tinggi.',
            'status'      => 'available',
            'rank'        => 'B',
            'reward_exp'  => 300,
            'reward_gold' => 500,
            'doomsday'    => '5 Days Left',
        ]);

        // ── Accepted Quests ──────────────────────────────────
        Quest::create([
            'guild_id'    => $guild->id,
            'title'       => 'Design Landing Page UI',
            'description' => 'Buat desain antarmuka depan yang memukau untuk menarik pengguna baru.',
            'status'      => 'accepted',
            'rank'        => 'A',
            'reward_exp'  => 450,
            'reward_gold' => 750,
            'doomsday'    => '3 Days Left',
        ]);

        // ── In Battle Quests ─────────────────────────────────
        Quest::create([
            'guild_id'    => $guild->id,
            'title'       => 'Write Unit Tests for API',
            'description' => 'Coverage saat ini 12%. Target minimal 80% sebelum deploy.',
            'status'      => 'in_battle',
            'rank'        => 'B',
            'reward_exp'  => 350,
            'reward_gold' => 600,
            'doomsday'    => '2 Days Left',
        ]);

        Quest::create([
            'guild_id'    => $guild->id,
            'title'       => 'Integrate Payment Gateway',
            'description' => 'Hubungkan Midtrans ke sistem checkout.',
            'status'      => 'in_battle',
            'rank'        => 'A',
            'reward_exp'  => 500,
            'reward_gold' => 900,
            'doomsday'    => '4 Days Left',
        ]);

        // ── Cleared Quests ───────────────────────────────────
        Quest::create([
            'guild_id'    => $guild->id,
            'title'       => 'Setup CI/CD Pipeline',
            'description' => 'GitHub Actions berhasil dikonfigurasi untuk auto-deploy.',
            'status'      => 'cleared',
            'rank'        => 'C',
            'reward_exp'  => 200,
            'reward_gold' => 300,
            'doomsday'    => null,
        ]);
    }
}