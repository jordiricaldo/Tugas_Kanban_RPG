<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;

Route::options('{any}', fn() => response()->json([], 200))->where('any', '.*');

// ── Auth (public) ────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Guild leaderboard (public)
Route::get('/guilds/leaderboard', [AuthController::class, 'guildLeaderboard']);

// ── Protected ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    Route::get('/board',          [BoardController::class, 'index']);
    Route::get('/guild',          [BoardController::class, 'guild']);
    Route::get('/leaderboard',    [BoardController::class, 'leaderboard']);

    Route::post('/quests',        [BoardController::class, 'store']);
    Route::patch('/quests/{id}',  [BoardController::class, 'update']);
    Route::delete('/quests/{id}', [BoardController::class, 'destroy']);

    Route::get('/players',          [BoardController::class, 'players']);
    Route::delete('/players/{id}',  [BoardController::class, 'destroyPlayer']);
});