<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guild;
use App\Models\Player;
use App\Models\GuildJoinRequest;
use Illuminate\Http\Request;

class GuildController extends Controller
{
    // POST /api/guilds/create — user yang sudah login tapi belum punya guild, buat guild baru
    public function create(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($player->guild_id) {
            return response()->json(['message' => 'Kamu sudah berada di dalam guild.'], 422);
        }

        $request->validate([
            'guild_name' => 'required|string|max:100',
        ]);

        $guild = Guild::create([
            'name'            => $request->guild_name,
            'guild_master_id' => $request->user()->id,
        ]);

        $player->update([
            'guild_id' => $guild->id,
            'role'     => 'guild_master',
        ]);

        // Tolak semua pending request lainnya
        \App\Models\GuildJoinRequest::where('player_id', $player->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json([
            'player' => array_merge($player->fresh()->toArray(), ['achievements' => $player->achievements]),
            'guild'  => $guild,
        ]);
    }

    // GET /api/guilds/search?q=nama  — cari guild publik
    public function search(Request $request)
    {
        $q = $request->query('q', '');

        $guilds = Guild::withCount('players')
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderByDesc('level')
            ->limit(20)
            ->get()
            ->map(fn($g) => [
                'id'            => $g->id,
                'name'          => $g->name,
                'level'         => $g->level,
                'exp'           => $g->exp,
                'gold'          => $g->gold,
                'invite_code'   => $g->invite_code,
                'players_count' => $g->players_count,
            ]);

        return response()->json($guilds);
    }

    // POST /api/guilds/{id}/request-join — player minta join guild
    public function requestJoin(Request $request, $guildId)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($player->guild_id) {
            return response()->json(['message' => 'Kamu sudah berada di dalam guild.'], 422);
        }

        $guild = Guild::findOrFail($guildId);

        // Cek apakah sudah punya pending request ke guild ini
        $existing = GuildJoinRequest::where('guild_id', $guildId)
                        ->where('player_id', $player->id)
                        ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json(['message' => 'Kamu sudah mengirim request ke guild ini.'], 422);
            }
            if ($existing->status === 'rejected') {
                // Boleh request ulang
                $existing->update(['status' => 'pending']);
                return response()->json(['message' => 'Request join dikirim ulang.', 'request' => $existing]);
            }
        }

        $joinReq = GuildJoinRequest::create([
            'guild_id'  => $guildId,
            'player_id' => $player->id,
            'status'    => 'pending',
        ]);

        return response()->json(['message' => 'Request join berhasil dikirim! Tunggu persetujuan GM.', 'request' => $joinReq], 201);
    }

    // GET /api/guilds/join-requests — GM lihat semua pending request di guildnya
    public function listJoinRequests(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($player->role !== 'guild_master') {
            return response()->json(['message' => 'Hanya Guild Master yang bisa melihat join request.'], 403);
        }

        $requests = GuildJoinRequest::with('player')
            ->where('guild_id', $player->guild_id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'status'     => $r->status,
                'created_at' => $r->created_at,
                'player'     => [
                    'id'             => $r->player->id,
                    'name'           => $r->player->name,
                    'class'          => $r->player->class,
                    'level'          => $r->player->level,
                    'quests_cleared' => $r->player->quests_cleared,
                ],
            ]);

        return response()->json($requests);
    }

    // POST /api/guilds/join-requests/{id}/accept — GM accept
    public function acceptRequest(Request $request, $requestId)
    {
        $me = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($me->role !== 'guild_master') {
            return response()->json(['message' => 'Hanya Guild Master yang bisa menerima request.'], 403);
        }

        $joinReq = GuildJoinRequest::where('guild_id', $me->guild_id)
                        ->where('status', 'pending')
                        ->findOrFail($requestId);

        $player = $joinReq->player;

        if ($player->guild_id) {
            $joinReq->update(['status' => 'rejected']);
            return response()->json(['message' => 'Player sudah bergabung guild lain.'], 422);
        }

        // Update player masuk guild
        $player->update([
            'guild_id' => $me->guild_id,
            'role'     => 'member',
        ]);

        $joinReq->update(['status' => 'accepted']);

        // Tolak semua pending request player ini di guild lain
        GuildJoinRequest::where('player_id', $player->id)
            ->where('status', 'pending')
            ->where('id', '!=', $joinReq->id)
            ->update(['status' => 'rejected']);

        return response()->json(['message' => "✅ {$player->name} berhasil bergabung ke guild!"]);
    }

    // POST /api/guilds/join-requests/{id}/reject — GM reject
    public function rejectRequest(Request $request, $requestId)
    {
        $me = Player::where('user_id', $request->user()->id)->firstOrFail();

        if ($me->role !== 'guild_master') {
            return response()->json(['message' => 'Hanya Guild Master yang bisa menolak request.'], 403);
        }

        $joinReq = GuildJoinRequest::where('guild_id', $me->guild_id)
                        ->where('status', 'pending')
                        ->findOrFail($requestId);

        $joinReq->update(['status' => 'rejected']);

        return response()->json(['message' => "❌ Request ditolak."]);
    }

    // GET /api/guilds/my-requests — player lihat status request join miliknya
    public function myRequests(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->firstOrFail();

        $requests = GuildJoinRequest::with('guild')
            ->where('player_id', $player->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'status'     => $r->status,
                'updated_at' => $r->updated_at,
                'guild' => [
                    'id'    => $r->guild->id,
                    'name'  => $r->guild->name,
                    'level' => $r->guild->level,
                ],
            ]);

        return response()->json($requests);
    }
}