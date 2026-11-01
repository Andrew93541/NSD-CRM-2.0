<?php

use App\Http\Middleware\BlockSensitivePaths;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\SetUserTheme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(BlockSensitivePaths::class);

        $middleware->web(append: [
            SetUserLocale::class,
            SetUserTheme::class,
            SetTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
