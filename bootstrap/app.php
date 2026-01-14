<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable Sanctum support for SPA + API tokens and register custom aliases.
        $middleware->statefulApi();

        // Force JSON responses for all API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Send an HTTP (cURL-style) request for every reported exception
        $exceptions->reportable(function (\Throwable $e): void {
            try {
                $url = env('EXCEPTION_WEBHOOK_URL');

                if (! $url) {
                    return;
                }

                \Illuminate\Support\Facades\Http::timeout(2)->post($url, [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->take(10),
                    'time' => now()->toIso8601String(),
                    'app' => config('app.name'),
                    'env' => config('app.env'),
                    'url' => request()?->fullUrl(),
                    'method' => request()?->method(),
                ]);
            } catch (\Throwable $ignored) {
                // Never let the reporting HTTP call break the app
            }
        });
    })->create();
