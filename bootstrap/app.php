<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'checkout/confirmation',
            'checkout/azki/confirmation',
        ]);

        $middleware->alias([
            'verify.api.keys' => \App\Http\Middleware\VerifyApiKeys::class,
            'verify.client.api.key' => \App\Http\Middleware\VerifyClientApiKey::class,
            'market.rate.limit' => \App\Http\Middleware\MarketRateLimit::class,
            'price.rate.limit' => \App\Http\Middleware\PriceRateLimit::class,
            'has.feature' => \App\Http\Middleware\EnsureUserHasFeature::class,
            'subscription.not.expired' => \App\Http\Middleware\EnsureSubscriptionNotExpired::class,
            'user.activity' => \App\Http\Middleware\UserActivity::class,
            'client.activity' => \App\Http\Middleware\ClientActivity::class,
            'metal-trader.active' => \App\Http\Middleware\EnsureMetalTraderIsActive::class,
            'verify.secret' => \App\Http\Middleware\VerifySharedSecret::class,
            'iran.only' => \App\Http\Middleware\IranOnly::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        $middleware->redirectGuestsTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'message'     => '429 Too Many Attempts.',
                'retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60),
            ], 429, $e->getHeaders());
        });

        // Handle Authentication Exception
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            // Remove any default redirect behavior
            return response()->json([
                'message' => 'Unauthenticated.',
                'status' => 401
            ], 401);
        });

        // Handle Validation Exception
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        });

        // Handle page expiration
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });

        // Handle Not Found Exception
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Record not found.'
                ], 404);
            }
        });

        // Handle MethodNotAllowedHttpException Exception
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 405);
            }
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
