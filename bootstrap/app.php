<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\Firewall;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Seo\NotFound;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require __DIR__.'/nova-env.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, damit der Installer vor jeder anderen Prüfung (z. B. Anmeldung) greift.
        $middleware->append(EnsureInstalled::class);
        $middleware->prepend(Firewall::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [MaintenanceMode::class]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'feature' => RequireFeature::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Nicht gefundene Seiten: erst Weiterleitungen prüfen, sonst freundliche Seite zeigen und protokollieren
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => NotFound::handle($request));
    })->create();
