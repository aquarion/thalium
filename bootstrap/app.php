<?php

use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\TrimStrings;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\LibrisServiceProvider;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        HorizonServiceProvider::class,
        RouteServiceProvider::class,
        LibrisServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->append(CheckForMaintenanceMode::class);

        $middleware->throttleApi('60,1');

        $middleware->replace(Illuminate\Foundation\Http\Middleware\TrimStrings::class, TrimStrings::class);
        $middleware->replace(TrustProxies::class, App\Http\Middleware\TrustProxies::class);

        $middleware->alias([
            'bindings' => SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
