<?php

namespace App\Events;

use App\Models\DealingGroup;
use App\Services\EncryptionService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealingGroupUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public DealingGroup $dealingGroup) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("dealing-group-{$this->dealingGroup->id}");
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        $this->dealingGroup->load('metalItemConfigs');
        return EncryptionService::encrypt($this->dealingGroup);
    }
}
