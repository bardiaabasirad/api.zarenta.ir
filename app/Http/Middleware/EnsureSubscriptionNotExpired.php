<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionNotExpired
{
    public function handle(Request $request, Closure $next, $featureSlug): Response
    {
        $apiClient = $request->attributes->get('api_client');

        if (! $apiClient->subscriptionNotExpired($featureSlug)) {
            return response()->json([
                'message' => 'Subscription expired.',
                'error_code' => 'SUBSCRIPTION_EXPIRED'
            ], 403);
        }

        return $next($request);
    }
}
