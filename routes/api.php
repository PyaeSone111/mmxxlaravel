<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MediafireController;
use App\Http\Controllers\Api\VideoController;
use Illuminate\Support\Facades\Route;

// Public: admin login
Route::post('/admin/login', [AuthController::class, 'login']);

// Admin-only API (Sanctum + admin middleware)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('videos', VideoController::class)->except(['update']);
    Route::post('videos/{video}', [VideoController::class, 'update']); // FormData support
    Route::post('mediafire/metadata', [MediafireController::class, 'fetchMetadata']);
});

// Optional: public API for mmxx frontend (no auth) - categories and videos list
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/videos', [VideoController::class, 'index']);
Route::get('/videos/{slug}', [VideoController::class, 'showBySlug'])->where('slug', '[a-z0-9-]+');
Route::get('/categories/{category}', [CategoryController::class, 'show']);
