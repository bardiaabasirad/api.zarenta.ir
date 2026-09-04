<?php

namespace App\Http\Middleware;

use App\Models\MetalTrader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyClientApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');

        if (!$apiKey) {
            return response()->json([
                'error' => 'api_key_is_missing'
            ], 401);
        }

        $metalTrader = MetalTrader::where('api_key', $apiKey)->first();

        if (!$metalTrader) {
            return response()->json([
                'error' => 'invalid_api_key'
            ], 401);
        }

        // Add API client to the request for later use
        $request->attributes->add(['api_client' => $metalTrader]);

        return $next($request);
    }
}
