<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Services\MetalTraderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly MetalTraderNotificationService $notificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getUnreadForTrader($request->user()->id);

        return response()->json(['data' => $notifications]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $success = $this->notificationService->markAsRead($id, $request->user()->id);

        if (!$success) {
            return response()->json(['message' => 'اطلاعیه یافت نشد.'], 404);
        }

        return response()->json(['message' => 'اطلاعیه خوانده شد.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json([
            'message' => 'تمام اطلاعیه‌ها خوانده شدند.',
            'count' => $count,
        ]);
    }
}
