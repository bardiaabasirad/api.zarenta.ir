<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySharedSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Api-Secret', '');
        $expected = (string) config('services.internal.secret');

        // hash_equals برای جلوگیری از timing attack
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
