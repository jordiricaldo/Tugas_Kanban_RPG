<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BoardController;

Route::options('{any}', fn() => response()->json([], 200))->where('any', '.*');

// Board
Route::get('/board',       [BoardController::class, 'index']);
Route::get('/guild',       [BoardController::class, 'guild']);
Route::get('/leaderboard', [BoardController::class, 'leaderboard']);

// Quest CRUD
Route::post('/quests',          [BoardController::class, 'store']);
Route::patch('/quests/{id}',    [BoardController::class, 'update']);
Route::delete('/quests/{id}',   [BoardController::class, 'destroy']);

// Player CRUD
Route::get('/players',          [BoardController::class, 'players']);
Route::post('/players',         [BoardController::class, 'storePlayer']);
Route::delete('/players/{id}',  [BoardController::class, 'destroyPlayer']);