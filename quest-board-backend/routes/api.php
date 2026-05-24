<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BoardController;

Route::get('/board', [BoardController::class, 'index']);
