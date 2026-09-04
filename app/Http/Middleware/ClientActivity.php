<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('metal-trader-api')->check()) {
            $client = Auth::guard('metal-trader-api')->user();

            if (!$client->last_seen || $client->last_seen->diffInMinutes(now()) >= 2) {
                $client->timestamps = false;
                $client->last_seen = now();
                $client->save();
                $client->timestamps = true;
            }
        }

        return $next($request);
    }
}
