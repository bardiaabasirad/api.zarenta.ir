<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMetalTraderIsActive
{
    /**
     * اطمینان از فعال بودن حساب metal trader.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $trader = $request->user('metal-trader-api');

        if (! $trader) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($trader->status === 'inactive') {
            return response()->json([
                'message' => 'حساب کاربری شما غیرفعال است.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
