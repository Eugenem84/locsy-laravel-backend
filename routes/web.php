<?php

use App\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Route;

// API-бэкенд не отдаёт контент: корень уводим на SPA-фронтенд.
Route::get('/', fn () => redirect()->away(config('app.frontend_url')));

// Подтверждение почты по ссылке из письма. Ссылка подписанная (проверяется
// внутри контроллера), лимит — чтобы подписанные URL не перебирали.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:6,1')
    ->name('verification.verify');

