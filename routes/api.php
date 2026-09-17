<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PhotographerController;
use Illuminate\Support\Facades\Route;

Route::get('/locations/by-bounds', [LocationController::class, 'getLocationsByBounds']);
Route::get('/locations', [LocationController::class, 'index']);
Route::get('/location/{id}', [LocationController::class, 'show']);
Route::get('/photographers/{id}', [PhotographerController::class, 'show']);
Route::apiResource('cities', CityController::class)->only(['index']);
Route::apiResource('categories', CategoryController::class)->only(['index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/user/locations', [AuthController::class, 'myLocations']);
    Route::get('/user/photos', [AuthController::class, 'myPhotos']);
    Route::put('/user/photographer-profile', [AuthController::class, 'updatePhotographerProfile']);
    Route::post('locations', [LocationController::class, 'store']);
    Route::post('/locations/{location}/favorite', [FavoriteController::class, 'add']);
    Route::delete('/locations/{location}/favorite', [FavoriteController::class, 'remove']);
    Route::get('/favorites', [FavoriteController::class, 'list']);
    Route::post('/locations/{location}/photos', [PhotoController::class, 'store']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::put('/user/city', [AuthController::class, 'updateUserCity']);
    Route::post('/user/avatar', [AuthController::class, 'updateAvatar']);
});

Route::middleware('web')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    // Жёсткий лимит на попытки входа (защита от перебора паролей)
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Восстановление доступа: письмо со ссылкой и установка нового пароля.
    // Лимит по email+IP — чтобы через форму не спамили письмами на чужой адрес.
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset');
});

Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
