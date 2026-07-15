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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'premium' => \App\Http\Middleware\EnsurePremium::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An expired or mangled verification link lands real users on Laravel's
        // bare "403 invalid signature" page. Send them into the SPA instead,
        // where the verification banner offers a one-click resend.
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, \Illuminate\Http\Request $request) {
            if ($request->routeIs('verification.verify')) {
                $appUrl = rtrim(config('services.stripe.frontend_url') ?: config('app.url'), '/');

                return redirect()->away($appUrl.'/dashboard?verified=expired');
            }
        });
    })->create();
