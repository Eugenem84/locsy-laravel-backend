<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PhotographerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/locations/by-bounds', [LocationController::class, 'getLocationsByBounds']);
Route::get('/locations', [LocationController::class, 'index']);
Route::get('/location/{id}', [LocationController::class, 'show']);
Route::get('/photographers/{id}', [PhotographerController::class, 'show']);
Route::apiResource('cities', CityController::class)->only(['index']);
Route::apiResource('categories', CategoryController::class)->only(['index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('locations', [LocationController::class, 'store']);
    Route::post('/locations/{location}/favorite', [FavoriteController::class, 'add']);
    Route::delete('/locations/{location}/favorite', [FavoriteController::class, 'remove']);
    Route::get('/favorites', [FavoriteController::class, 'list']);
    Route::post('/locations/{location}/photos', [PhotoController::class, 'store']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::put('/user/city', [AuthController::class, 'updateUserCity']);
    Route::post('/user/avatar', [AuthController::class, 'updateAvatar']);
});


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
