<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Events\NotificationsBulkRead;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function getUnreadNotificationsForAdmin(int $adminId): Collection
    {
        return Notification::forAdmin($adminId)
            ->with(['creator', 'targetAdmin'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getUnreadCountForAdmin(int $adminId): int
    {
        return Notification::forAdmin($adminId)->count();
    }

    public function createPublicSingleRead(array $data): Notification
    {
        $notification = Notification::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'notification_type' => 'public_single_read',
            'created_by' => $data['created_by'],
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }

    public function createPublicMultiRead(array $data): Notification
    {
        $notification = Notification::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'notification_type' => 'public_multi_read',
            'created_by' => $data['created_by'],
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }

    public function createPersonal(array $data): Notification
    {
        $notification = Notification::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'notification_type' => 'personal',
            'target_admin_id' => $data['target_admin_id'],
            'created_by' => $data['created_by'],
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }

    public function markAsRead(int $notificationId, int $adminId): bool
    {
        $notification = Notification::findOrFail($notificationId);

        if (!$notification->canBeReadBy($adminId)) {
            return false;
        }

        if (!$notification->isReadByAdmin($adminId)) {
            $notification->markAsReadBy($adminId);
        }

        return true;
    }

    public function markAllPublicSingleReadAsRead(int $adminId): int
    {
        $unreadPublicSingleReads = Notification::where('notification_type', 'public_single_read')
            ->where('is_globally_read', false)
            ->get();

        $count = 0;
        foreach ($unreadPublicSingleReads as $notification) {
            $notification->markAsReadBy($adminId);
            $count++;
        }

        return $count;
    }

    public function markAllAsRead(int $adminId): void
    {
        $unreadNotifications = $this->getUnreadNotificationsForAdmin($adminId);

        foreach ($unreadNotifications as $notification) {
            $notification->markAsReadBy($adminId);
        }
    }

    public function markAllAsReadForAdmin(int $adminId, bool $broadcast = true): array
    {
        $unreadNotifications = $this->getUnreadNotificationsForAdmin($adminId);

        $stats = [
            'total' => 0,
            'public_single_read' => 0,
            'public_multi_read' => 0,
            'personal' => 0,
        ];

        $notificationIds = [];

        \DB::transaction(function () use ($unreadNotifications, $adminId, &$stats, &$notificationIds) {
            foreach ($unreadNotifications as $notification) {
                // از متد داخلی بدون Event استفاده می‌کنیم
                $this->markAsReadWithoutEvent($notification, $adminId);

                $stats['total']++;
                $stats[$notification->notification_type]++;
                $notificationIds[] = $notification->id;
            }
        });

        // پخش یک Event واحد برای همه
        if ($broadcast && $stats['total'] > 0) {
            event(new NotificationsBulkRead($adminId, $stats, $notificationIds));
        }

        return $stats;
    }

    /**
     * علامت‌گذاری بدون پخش Event (برای استفاده داخلی)
     */
    private function markAsReadWithoutEvent(Notification $notification, int $adminId): void
    {
        \App\Models\NotificationRead::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'admin_id' => $adminId,
            ],
            [
                'read_at' => now(),
            ]
        );

        if ($notification->notification_type === 'public_single_read') {
            $notification->update(['is_globally_read' => true]);
        }
    }

    public function deleteNotification(int $notificationId, int $adminId): bool
    {
        $notification = Notification::find($notificationId);

        if (!$notification) {
            return false;
        }

        if ($notification->created_by !== $adminId) {
            return false;
        }

        $notification->delete();
        return true;
    }

    public function getAllNotificationsForAdmin(int $adminId): Collection
    {
        return Notification::where(function ($query) use ($adminId) {
            $query->whereIn('notification_type', ['public_single_read', 'public_multi_read'])
                ->orWhere(function ($subQuery) use ($adminId) {
                    $subQuery->where('notification_type', 'personal')
                        ->where('target_admin_id', $adminId);
                });
        })
            ->with(['creator', 'targetAdmin', 'reads' => function ($query) use ($adminId) {
                $query->where('admin_id', $adminId);
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) use ($adminId) {
                $notification->is_read = $notification->isReadByAdmin($adminId);
                return $notification;
            });
    }
}
