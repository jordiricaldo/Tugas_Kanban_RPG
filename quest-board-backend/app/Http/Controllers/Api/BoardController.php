<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guild;
use App\Models\Quest;

class BoardController extends Controller
{
    public function index()
    {
        // Ambil data guild pertama dan semua quests
        $guild = Guild::first();
        $quests = Quest::all();

        // Kirim sebagai format JSON
        return response()->json([
            'guild' => $guild,
            'quests' => $quests
        ]);
    }
}