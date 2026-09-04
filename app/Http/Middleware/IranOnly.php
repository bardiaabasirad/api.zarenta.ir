<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;

class IranOnly
{
    private array $allowedBots = ['Googlebot', 'bingbot', 'Baiduspider', 'YandexBot', 'facebookexternalhit'];

    public function handle(Request $request, Closure $next)
    {
        $ua = $request->userAgent() ?? '';
        foreach ($this->allowedBots as $bot) {
            if (stripos($ua, $bot) !== false) return $next($request);
        }

        $position = Location::get($request->ip());
        if ($position && strtoupper($position->countryCode) !== 'IR') {
            return response()->json([
                'code'    => 'GEO_BLOCKED',
                'message' => 'دسترسی از خارج از ایران مجاز نیست.',
            ], 403);
        }

        return $next($request);
    }
}
