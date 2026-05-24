<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guild;
use App\Models\Quest;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    // GET /api/board — ambil semua data guild & quest
    public function index()
    {
        $guild = Guild::first();
        $quests = Quest::all();

        return response()->json([
            'guild'  => $guild,
            'quests' => $quests,
        ]);
    }

    // POST /api/quests — buat quest baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'rank'        => 'required|in:S,A,B,C',
            'reward_exp'  => 'required|integer|min:0',
            'reward_gold' => 'required|integer|min:0',
            'doomsday'    => 'nullable|string|max:100',
        ]);

        $guild = Guild::first();

        $quest = Quest::create([
            'guild_id'    => $guild->id,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status'      => 'available',
            'rank'        => $validated['rank'],
            'reward_exp'  => $validated['reward_exp'],
            'reward_gold' => $validated['reward_gold'],
            'doomsday'    => $validated['doomsday'] ?? null,
        ]);

        return response()->json($quest, 201);
    }

    // PATCH /api/quests/{id} — update quest (status, judul, dll)
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
        ]);

        $quest->update($validated);

        // Kalau status berubah ke cleared, tambah reward ke guild
        if (isset($validated['status']) && $validated['status'] === 'cleared') {
            $guild = Guild::first();
            $guild->exp  += $quest->reward_exp;
            $guild->gold += $quest->reward_gold;

            // Naik level setiap 1000 EXP
            while ($guild->exp >= $guild->level * 1000) {
                $guild->exp  -= $guild->level * 1000;
                $guild->level += 1;
            }

            $guild->save();
        }

        return response()->json($quest);
    }

    // DELETE /api/quests/{id} — hapus quest
    public function destroy($id)
    {
        $quest = Quest::findOrFail($id);
        $quest->delete();

        return response()->json(['message' => 'Quest berhasil dihapus.']);
    }

    // GET /api/guild — ambil data guild saja (untuk refresh setelah clear)
    public function guild()
    {
        return response()->json(Guild::first());
    }
}