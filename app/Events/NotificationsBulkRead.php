<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;

class NotificationsBulkRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $adminId;
    public array $stats;
    public array $notificationIds;

    public function __construct(int $adminId, array $stats, array $notificationIds)
    {
        $this->adminId = $adminId;
        $this->stats = $stats;
        $this->notificationIds = $notificationIds;
    }

    public function broadcastOn(): array
    {
        return [
            // فقط به کانال خصوصی ادمینی که همه را خوانده
            new PrivateChannel('notifications-admin-' . $this->adminId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'admin_id' => $this->adminId,
            'notification_ids' => $this->notificationIds,
            'stats' => $this->stats,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notifications-bulk-read';
    }
}
