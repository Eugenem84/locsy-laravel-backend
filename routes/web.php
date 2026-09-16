<?php

use Illuminate\Support\Facades\Route;

// API-бэкенд не отдаёт контент: корень уводим на SPA-фронтенд.
Route::get('/', fn () => redirect()->away(config('app.frontend_url')));
