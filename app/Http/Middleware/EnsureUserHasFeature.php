<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasFeature
{
    public function handle(Request $request, Closure $next, $featureSlug): Response
    {
        $apiClient = $request->attributes->get('api_client');

        if (! $apiClient->hasFeature($featureSlug)) {
            return response()->json([
                'error' => 'not_allowed'
            ], 429);
        }

        return $next($request);
    }
}
