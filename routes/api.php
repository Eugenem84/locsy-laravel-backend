<?php

use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::get('/cities', [CityController::class, 'index']);

Route::get('/locations', [LocationController::class, 'index']);
