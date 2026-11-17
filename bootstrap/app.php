<?php

use App\Http\Middleware\EnsureRolePrefixMatches;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->web(append: [
            EnsureRolePrefixMatches::class,
        ]);
        $middleware->appendToGroup('web', PreventBackHistory::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
