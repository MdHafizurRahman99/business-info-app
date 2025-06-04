<?php

use App\Http\Controllers\Api\BusinessController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Business API routes
// Route::prefix('businesses')->group(function () {
//     Route::get('/search', [BusinessController::class, 'search']);
//     Route::get('/', [BusinessController::class, 'index']);
//     Route::get('/{id}', [BusinessController::class, 'show']);
// });

// Business API routes
Route::prefix('businesses')->group(function () {
    Route::get('/test-api', [BusinessController::class, 'testApi']);
    Route::get('/search', [BusinessController::class, 'search']);
    Route::get('/stats', [BusinessController::class, 'stats']);
    Route::get('/', [BusinessController::class, 'index']);
    Route::get('/{id}', [BusinessController::class, 'show']);
});

