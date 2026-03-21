<?php

use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/cities', [CityController::class, 'index']);

Route::get('/locations', [LocationController::class, 'index']);
Route::get('/location/{id}', [LocationController::class, 'show']);
