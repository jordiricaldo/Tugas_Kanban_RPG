<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guild;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'email'        => 'required|email|unique:users',
            'password'     => 'required|string|min:6|confirmed',
            'class'        => 'required|in:Warrior,Mage,Rogue,Archer',
            'action'       => 'required|in:create_guild,join_guild',
            'guild_name'   => 'required_if:action,create_guild|string|max:100',
            'invite_code'  => 'required_if:action,join_guild|string',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        $avatarMap = ['Warrior' => 'warrior', 'Mage' => 'mage', 'Rogue' => 'rogue', 'Archer' => 'archer'];

        if ($validated['action'] === 'create_guild') {
            // Buat guild baru, user jadi Guild Master
            $guild = Guild::create([
                'name'            => $validated['guild_name'],
                'guild_master_id' => $user->id,
            ]);

            $player = Player::create([
                'guild_id' => $guild->id,
                'user_id'  => $user->id,
                'name'     => $validated['name'],
                'class'    => $validated['class'],
                'avatar'   => $avatarMap[$validated['class']],
                'role'     => 'guild_master',
            ]);
        } else {
            // Join guild existing via invite code
            $guild = Guild::where('invite_code', strtoupper($validated['invite_code']))->first();
            if (!$guild) {
                $user->delete();
                return response()->json(['message' => 'Invite code tidak valid.'], 422);
            }

            $player = Player::create([
                'guild_id' => $guild->id,
                'user_id'  => $user->id,
                'name'     => $validated['name'],
                'class'    => $validated['class'],
                'avatar'   => $avatarMap[$validated['class']],
                'role'     => 'member',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'user'   => $user,
            'player' => array_merge($player->toArray(), ['achievements' => $player->achievements]),
            'guild'  => $guild,
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $player = Player::with('guild')->where('user_id', $user->id)->first();
        $guild  = $player?->guild;

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'user'   => $user,
            'player' => $player ? array_merge($player->toArray(), ['achievements' => $player->achievements]) : null,
            'guild'  => $guild,
        ]);
    }

    // POST /api/auth/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil.']);
    }

    // GET /api/auth/me
    public function me(Request $request)
    {
        $user   = $request->user();
        $player = Player::with('guild')->where('user_id', $user->id)->first();
        $guild  = $player?->guild;

        return response()->json([
            'user'   => $user,
            'player' => $player ? array_merge($player->toArray(), ['achievements' => $player->achievements]) : null,
            'guild'  => $guild,
        ]);
    }

    // GET /api/guilds/leaderboard - leaderboard antar guild
    public function guildLeaderboard()
    {
        $guilds = Guild::withCount('players')
            ->with(['players' => function ($q) {
                $q->orderByDesc('quests_cleared');
            }])
            ->get()
            ->map(function ($guild) {
                $totalCleared = $guild->players->sum('quests_cleared');
                return [
                    'id'             => $guild->id,
                    'name'           => $guild->name,
                    'level'          => $guild->level,
                    'exp'            => $guild->exp,
                    'gold'           => $guild->gold,
                    'players_count'  => $guild->players_count,
                    'total_cleared'  => $totalCleared,
                    'invite_code'    => $guild->invite_code,
                ];
            })
            ->sortByDesc('total_cleared')
            ->values();

        return response()->json($guilds);
    }
}