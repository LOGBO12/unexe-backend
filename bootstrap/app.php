<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

                $middleware->prepend([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // alias de tes middlewares existants
        $middleware->alias([
            'verified'          => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role'              => \App\Http\Middleware\RoleMiddleware::class,
            'can_access_forum'  => \App\Http\Middleware\CanAccessForumMiddleware::class,
            'profile_complete'  => \App\Http\Middleware\ProfileCompleteMiddleware::class,
            'can.access.forum'  => \App\Http\Middleware\CanAccessForumMiddleware::class,
            'profile.complete'  => \App\Http\Middleware\ProfileCompleteMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();