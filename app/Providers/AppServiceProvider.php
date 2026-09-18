<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Общий лимит на все API-запросы
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Отдельный жёсткий лимит на попытки входа: 5 запросов в минуту на email+IP
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by($request->input('email').'|'.$request->ip()));

        // Регистрация: 5 запросов в минуту на IP — форма не должна становиться
        // инструментом массового создания аккаунтов
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip()));

        // Повторная отправка письма подтверждения: 3 в минуту на пользователя+IP
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(3)
            ->by(($request->user()?->id ?: $request->ip()).'|'.$request->ip()));

        // Восстановление пароля: 5 запросов в минуту на email+IP — форма не должна
        // становиться инструментом спама письмами по чужому адресу
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
