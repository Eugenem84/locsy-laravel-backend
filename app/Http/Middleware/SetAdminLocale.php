<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Русский интерфейс админки Filament.
 *
 * Локаль переключается только для запросов панели «admin». Общий APP_LOCALE
 * остаётся прежним, чтобы сообщения валидации в API не менялись неожиданно
 * для SPA.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('ru');

        return $next($request);
    }
}
