<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKeys
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');
        $secretKey = $request->header('X-SECRET-KEY');

        // Replace these with your actual API keys or fetch from database
        $validApiKey = config('services.api.key');
        $validSecretKey = config('services.api.secret');

        if (!$apiKey || !$secretKey) {
            return response()->json([
                'error' => 'API key and Secret key are required'
            ], 401);
        }

        if ($apiKey !== $validApiKey || $secretKey !== $validSecretKey) {
            return response()->json([
                'error' => 'Invalid API credentials'
            ], 401);
        }

        return $next($request);
    }
}
