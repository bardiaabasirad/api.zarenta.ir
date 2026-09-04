<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->notification->notification_type === 'public_single_read' ||
            $this->notification->notification_type === 'public_multi_read') {
            // کانال عمومی برای همه ادمین‌ها
            $channels[] = new Channel('notifications-public');
        } elseif ($this->notification->notification_type === 'personal') {
            // کانال خصوصی برای ادمین مقصد
            $channels[] = new PrivateChannel('notifications-admin-' . $this->notification->target_admin_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'content' => $this->notification->content,
            'created_at' => $this->notification->created_at,
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification-created';
    }
}
