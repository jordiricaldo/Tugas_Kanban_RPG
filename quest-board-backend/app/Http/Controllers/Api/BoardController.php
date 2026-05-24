<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guild;
use App\Models\Quest;
use App\Models\Player;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    // GET /api/board  (butuh auth)
    public function index(Request $request)
    {
        $user   = $request->user();
        $player = Player::where('user_id', $user->id)->first();
        $guild  = $player?->guild ?? Guild::first();

        $quests  = Quest::with('player')->where('guild_id', $guild->id)->get();
        $players = Player::where('guild_id', $guild->id)
                         ->orderByDesc('quests_cleared')->get()
                         ->map(fn($p) => array_merge($p->toArray(), ['achievements' => $p->achievements]));

        return response()->json([
            'guild'      => $guild,
            'quests'     => $quests,
            'players'    => $players,
            'my_player'  => $player ? array_merge($player->toArray(), ['achievements' => $player->achievements]) : null,
        ]);
    }

    // GET /api/guild
    public function guild(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        return response()->json($player?->guild ?? Guild::first());
    }

    // ── Quest CRUD ────────────────────────────────────────────

    // POST /api/quests  — semua member bisa
    public function store(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'rank'        => 'required|in:S,A,B,C',
            'reward_exp'  => 'required|integer|min:0',
            'reward_gold' => 'required|integer|min:0',
            'doomsday'    => 'nullable|string|max:100',
            'player_id'   => 'nullable|exists:players,id',
        ]);

        $quest = Quest::create(array_merge($validated, [
            'guild_id' => $player->guild_id,
            'status'   => 'available',
        ]));

        return response()->json($quest->load('player'), 201);
    }

    // PATCH /api/quests/{id}  — semua member bisa update/move
    public function update(Request $request, $id)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();
        $quest  = Quest::where('guild_id', $player->guild_id)->findOrFail($id);

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status'      => 'sometimes|required|in:available,accepted,in_battle,cleared',
            'rank'        => 'sometimes|required|in:S,A,B,C',
            'reward_exp'  => 'sometimes|required|integer|min:0',
            'reward_gold' => 'sometimes|required|integer|min:0',
            'doomsday'    => 'sometimes|nullable|string|max:100',
            'player_id'   => 'sometimes|nullable|exists:players,id',
        ]);

        $wasCleared = $quest->status !== 'cleared'
                      && isset($validated['status'])
                      && $validated['status'] === 'cleared';

        $quest->update($validated);

        if ($wasCleared) {
            $guild = $quest->guild ?? Guild::find($player->guild_id);
            $guild->exp  += $quest->reward_exp;
            $guild->gold += $quest->reward_gold;
            while ($guild->exp >= $guild->level * 1000) {
                $guild->exp   -= $guild->level * 1000;
                $guild->level += 1;
            }
            $guild->save();

            if ($quest->player_id) {
                $qPlayer = Player::find($quest->player_id);
                if ($qPlayer) {
                    $qPlayer->exp            += $quest->reward_exp;
                    $qPlayer->gold           += $quest->reward_gold;
                    $qPlayer->quests_cleared += 1;
                    $qPlayer->streak         += 1;
                    if ($qPlayer->streak > $qPlayer->best_streak) {
                        $qPlayer->best_streak = $qPlayer->streak;
                    }
                    while ($qPlayer->exp >= $qPlayer->level * 500) {
                        $qPlayer->exp   -= $qPlayer->level * 500;
                        $qPlayer->level += 1;
                    }
                    $qPlayer->save();
                }
            }
        }

        if (isset($validated['status']) && $validated['status'] === 'available' && $quest->player_id) {
            $qPlayer = Player::find($quest->player_id);
            if ($qPlayer && $qPlayer->streak > 0) {
                $qPlayer->streak = 0;
                $qPlayer->save();
            }
        }

        return response()->json($quest->load('player'));
    }

    // DELETE /api/quests/{id}  — hanya Guild Master
    public function destroy(Request $request, $id)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($player->role !== 'guild_master') {
            return response()->json(['message' => 'Hanya Guild Master yang bisa menghapus quest.'], 403);
        }

        $quest = Quest::where('guild_id', $player->guild_id)->findOrFail($id);
        $quest->delete();

        return response()->json(['message' => 'Quest dihapus.']);
    }

    // ── Player CRUD ───────────────────────────────────────────

    // GET /api/players
    public function players(Request $request)
    {
        $player  = Player::where('user_id', $request->user()->id)->firstOrFail();
        $players = Player::where('guild_id', $player->guild_id)
                         ->orderByDesc('quests_cleared')->get()
                         ->map(fn($p) => array_merge($p->toArray(), ['achievements' => $p->achievements]));
        return response()->json($players);
    }

    // DELETE /api/players/{id}  — hanya Guild Master (kick member)
    public function destroyPlayer(Request $request, $id)
    {
        $me = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($me->role !== 'guild_master') {
            return response()->json(['message' => 'Hanya Guild Master yang bisa kick member.'], 403);
        }

        $target = Player::where('guild_id', $me->guild_id)->findOrFail($id);

        if ($target->role === 'guild_master') {
            return response()->json(['message' => 'Guild Master tidak bisa di-kick.'], 403);
        }

        $target->delete();
        return response()->json(['message' => 'Player dikeluarkan dari guild.']);
    }

    // GET /api/leaderboard
    public function leaderboard(Request $request)
    {
        $player  = Player::where('user_id', $request->user()->id)->firstOrFail();
        $players = Player::where('guild_id', $player->guild_id)
                         ->orderByDesc('quests_cleared')
                         ->orderByDesc('level')
                         ->orderByDesc('exp')
                         ->get()
                         ->map(fn($p) => array_merge($p->toArray(), ['achievements' => $p->achievements]));
        return response()->json($players);
    }
}