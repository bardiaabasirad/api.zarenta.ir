<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification, public int $adminId) {}

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->notification->notification_type === 'public_single_read') {
            // به همه ادمین‌ها اطلاع بده که این اطلاعیه خوانده شد
            $channels[] = new Channel('notifications-public');
        } elseif ($this->notification->notification_type === 'public_multi_read') {
            // فقط به ادمینی که خوانده اطلاع بده
            $channels[] = new PrivateChannel('notifications-admin-' . $this->adminId);
        } elseif ($this->notification->notification_type === 'personal') {
            // فقط به ادمین مقصد اطلاع بده
            $channels[] = new PrivateChannel('notifications-admin-' . $this->notification->target_admin_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notification->id,
            'notification_type' => $this->notification->notification_type,
            'read_by_admin_id' => $this->adminId,
            'is_globally_read' => $this->notification->is_globally_read,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification-read';
    }
}
