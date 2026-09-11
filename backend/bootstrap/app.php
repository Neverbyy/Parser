<?php

use App\Services\Yandex\Exceptions\YandexScraperException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookie-аутентификация SPA: запросы с доменов из SANCTUM_STATEFUL_DOMAINS
        // проходят через сессию и CSRF, остальные остаются stateless.
        $middleware->statefulApi();

        // На хостинге приложение стоит за балансировщиком, который
        // терминирует TLS. Без доверия к X-Forwarded-* Laravel считает
        // соединение незащищённым и не ставит secure-флаг на куку сессии —
        // браузер её отбрасывает, и вход перестаёт работать.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Ошибки парсера приходят на фронт единообразно: понятное сообщение
        // на русском плюс машиночитаемый код для ветвления в интерфейсе.
        $exceptions->render(function (YandexScraperException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode(),
            ], $e->httpStatus());
        });
    })->create();
