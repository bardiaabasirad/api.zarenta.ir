<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    /**
     * Mark the authenticated user as offline by updating last_seen.
     * Called on intentional leave, socket disconnect, or pagehide — not on join/HTTP activity.
     */
    public function leave(): JsonResponse
    {
        $user = Auth::guard('user-api')->user();

        if ($user) {
            $user->timestamps = false;
            $user->last_seen = now();
            $user->save();
            $user->timestamps = true;
        }

        return response()->json([
            'ok' => true,
            'last_seen' => $user?->last_seen?->toIso8601String(),
        ]);
    }
}
