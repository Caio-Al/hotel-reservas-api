<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// limite de 5 tentativas por minuto
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// exige token Sanctum e respeita o limite de 60 requisições por minuto.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});
