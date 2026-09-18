<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // В продакшене приложение стоит за reverse-proxy (Caddy дома + VPS-шлюз):
        // запрос приходит по HTTP, а корректная схема — в X-Forwarded-Proto.
        // Без доверия прокси Laravel генерирует http-ссылки (asset('storage/...'))
        // и браузер блокирует их как mixed content.
        $middleware->trustProxies(at: '*');

        // Ограничиваем частоту запросов к API (лимитер 'api' описан в AppServiceProvider)
        $middleware->throttleApi();

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Понятный ответ SPA, когда действие требует подтверждённой почты
        // (middleware `verified` для JSON-запроса отдаёт 403 этим сообщением).
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson()
                && $e->getStatusCode() === 403
                && $e->getMessage() === 'Your email address is not verified.') {
                return response()->json([
                    'message' => 'Подтвердите почту, чтобы пользоваться аккаунтом.',
                    'email_verified' => false,
                ], 403);
            }
        });
    })->create();
