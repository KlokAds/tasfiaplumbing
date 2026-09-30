<?php

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
        $middleware->redirectTo(
            guests: '/admin/login',
            users: '/admin/dashboard'
        );

        // Runs first: app key, database check, automatic migrations, first-run owner setup.
        $middleware->prepend(\App\Http\Middleware\AutoSetup::class);
        $middleware->append(\App\Http\Middleware\HandleRedirects::class);

        $middleware->web(append: [
            \App\Http\Middleware\SiteAccess::class,
            \App\Http\Middleware\UseLibraryFiles::class,
            \App\Http\Middleware\PublishScheduledArticles::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Inertia navigation that hits an error: load the branded error page as a full page
        // (instead of Inertia's technical popup), or return to the form with a clear message.
        $exceptions->respond(function ($response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (!$request->header('X-Inertia') || $status < 400 || $status === 422 || ($status >= 500 && config('app.debug'))) {
                return $response;
            }
            if ($request->isMethod('GET')) {
                return \Inertia\Inertia::location($request->fullUrl());
            }

            return back()->with('error', match (true) {
                $status === 419 => 'This page was open too long and expired. Please try again.',
                $status === 403 => 'You do not have permission to do this.',
                $status === 404 => 'That item no longer exists.',
                $status === 429 => 'Too many tries. Please wait a minute and try again.',
                default => 'Something went wrong (error ' . $status . '). Please try again.',
            });
        });
    })->create();
