<?php

namespace App\Services;

use App\Events\MetalTraderNotificationCreated;
use App\Models\MetalTraderNotification;
use Illuminate\Database\Eloquent\Collection;

class MetalTraderNotificationService
{
    public function create(array $data): MetalTraderNotification
    {
        $notification = MetalTraderNotification::create([
            'metal_trader_id' => $data['metal_trader_id'],
            'title' => $data['title'],
            'content' => $data['content'],
            'link' => $data['link'] ?? null,
            'type' => $data['type'] ?? null,
        ]);

        event(new MetalTraderNotificationCreated($notification));

        return $notification;
    }

    public function getUnreadForTrader(int $traderId): Collection
    {
        return MetalTraderNotification::query()
            ->where('metal_trader_id', $traderId)
            ->unread()
            ->orderByDesc('created_at')
            ->get();
    }

    public function markAsRead(int $notificationId, int $traderId): bool
    {
        $notification = MetalTraderNotification::query()
            ->where('metal_trader_id', $traderId)
            ->find($notificationId);

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllAsRead(int $traderId): int
    {
        return MetalTraderNotification::query()
            ->where('metal_trader_id', $traderId)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
