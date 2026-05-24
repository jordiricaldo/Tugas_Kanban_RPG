<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guild;
use App\Models\Quest;
use App\Models\Player;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    // GET /api/board
    public function index()
    {
        $guild   = Guild::first();
        $quests  = Quest::with('player')->get();
        $players = Player::orderByDesc('quests_cleared')->get()
                         ->map(fn($p) => array_merge($p->toArray(), ['achievements' => $p->achievements]));

        return response()->json([
            'guild'   => $guild,
            'quests'  => $quests,
            'players' => $players,
        ]);
    }

    // GET /api/guild
    public function guild()
    {
        return response()->json(Guild::first());
    }

    // ── Quest CRUD ────────────────────────────────────────────

    // POST /api/quests
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'rank'        => 'required|in:S,A,B,C',
            'reward_exp'  => 'required|integer|min:0',
            'reward_gold' => 'required|integer|min:0',
            'doomsday'    => 'nullable|string|max:100',
            'player_id'   => 'nullable|exists:players,id',
        ]);

        $guild = Guild::first();
        $quest = Quest::create(array_merge($validated, [
            'guild_id' => $guild->id,
            'status'   => 'available',
        ]));

        return response()->json($quest->load('player'), 201);
    }

    // PATCH /api/quests/{id}
    public function update(Request $request, $id)
    {
        $quest = Quest::findOrFail($id);

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
            // Reward ke guild
            $guild = Guild::first();
            $guild->exp  += $quest->reward_exp;
            $guild->gold += $quest->reward_gold;
            while ($guild->exp >= $guild->level * 1000) {
                $guild->exp   -= $guild->level * 1000;
                $guild->level += 1;
            }
            $guild->save();

            // Reward ke player jika ada
            if ($quest->player_id) {
                $player = Player::find($quest->player_id);
                if ($player) {
                    $player->exp            += $quest->reward_exp;
                    $player->gold           += $quest->reward_gold;
                    $player->quests_cleared += 1;
                    $player->streak         += 1;
                    if ($player->streak > $player->best_streak) {
                        $player->best_streak = $player->streak;
                    }
                    while ($player->exp >= $player->level * 500) {
                        $player->exp   -= $player->level * 500;
                        $player->level += 1;
                    }
                    $player->save();
                }
            }
        }

        // Reset streak kalau quest di-unassign atau di-reopen
        if (isset($validated['status']) && $validated['status'] === 'available' && $quest->player_id) {
            $player = Player::find($quest->player_id);
            if ($player && $player->streak > 0) {
                $player->streak = 0;
                $player->save();
            }
        }

        return response()->json($quest->load('player'));
    }

    // DELETE /api/quests/{id}
    public function destroy($id)
    {
        Quest::findOrFail($id)->delete();
        return response()->json(['message' => 'Quest dihapus.']);
    }

    // ── Player CRUD ───────────────────────────────────────────

    // GET /api/players
    public function players()
    {
        $players = Player::orderByDesc('quests_cleared')->get()
                         ->map(fn($p) => array_merge($p->toArray(), ['achievements' => $p->achievements]));
        return response()->json($players);
    }

    // POST /api/players
    public function storePlayer(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'class' => 'required|in:Warrior,Mage,Rogue,Archer',
        ]);

        $avatarMap = [
            'Warrior' => 'warrior',
            'Mage'    => 'mage',
            'Rogue'   => 'rogue',
            'Archer'  => 'archer',
        ];

        $guild  = Guild::first();
        $player = Player::create([
            'guild_id' => $guild->id,
            'name'     => $validated['name'],
            'class'    => $validated['class'],
            'avatar'   => $avatarMap[$validated['class']],
        ]);

        return response()->json(
            array_merge($player->toArray(), ['achievements' => $player->achievements]),
            201
        );
    }

    // DELETE /api/players/{id}
    public function destroyPlayer($id)
    {
        Player::findOrFail($id)->delete();
        return response()->json(['message' => 'Player dihapus.']);
    }

    // GET /api/leaderboard
    public function leaderboard()
    {
        $players = Player::orderByDesc('quests_cleared')
                         ->orderByDesc('level')
                         ->orderByDesc('exp')
                         ->get()
                         ->map(fn($p) => array_merge($p->toArray(), ['achievements' => $p->achievements]));
        return response()->json($players);
    }
}