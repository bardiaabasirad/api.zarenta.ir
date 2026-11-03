<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    /**
     * Mark the authenticated metal trader as offline by updating last_seen.
     * Called on intentional leave, socket disconnect, or pagehide — not on join/HTTP activity.
     */
    public function leave(): JsonResponse
    {
        $client = Auth::guard('metal-trader-api')->user();

        if ($client) {
            $client->timestamps = false;
            $client->last_seen = now();
            $client->save();
            $client->timestamps = true;
        }

        return response()->json(['ok' => true]);
    }
}
