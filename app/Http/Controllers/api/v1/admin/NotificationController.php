<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $adminId = auth()->id();
        $notifications = $this->notificationService->getUnreadNotificationsForAdmin($adminId);

        return response()->json($notifications);
    }

    public function all(Request $request): JsonResponse
    {
        $adminId = auth()->id();
        $notifications = $this->notificationService->getAllNotificationsForAdmin($adminId);

        return response()->json([
            'data' => $notifications,
            'count' => $notifications->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $adminId = auth()->id();
        $count = $this->notificationService->getUnreadCountForAdmin($adminId);

        return response()->json(['count' => $count]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'notification_type' => 'required|in:public_single_read,public_multi_read,personal',
            'target_admin_id' => 'required_if:notification_type,personal|exists:admins,id',
        ]);

        $validated['created_by'] = auth()->id();

        $notification = match ($validated['notification_type']) {
            'public_single_read' => $this->notificationService->createPublicSingleRead($validated),
            'public_multi_read' => $this->notificationService->createPublicMultiRead($validated),
            'personal' => $this->notificationService->createPersonal($validated),
        };

        return response()->json([
            'message' => 'اطلاعیه با موفقیت ایجاد شد',
            'data' => $notification
        ], 201);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $adminId = auth()->id();
        $success = $this->notificationService->markAsRead($id, $adminId);

        if (!$success) {
            return response()->json([
                'message' => 'شما اجازه خواندن این اطلاعیه را ندارید'
            ], 403);
        }

        return response()->json([
            'message' => 'اطلاعیه به عنوان خوانده شده علامت‌گذاری شد'
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $adminId = $request->user()->id;
            $stats = $this->notificationService->markAllAsReadForAdmin($adminId);

            return response()->json([
                'message' => 'تمام اطلاعیه‌های نخوانده علامت‌گذاری شدند',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در علامت‌گذاری اطلاعیه‌ها',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $adminId = auth()->id();
        $success = $this->notificationService->deleteNotification($id, $adminId);

        if (!$success) {
            return response()->json([
                'message' => 'اطلاعیه یافت نشد یا شما اجازه حذف آن را ندارید'
            ], 403);
        }

        return response()->json([
            'message' => 'اطلاعیه با موفقیت حذف شد'
        ]);
    }
}
