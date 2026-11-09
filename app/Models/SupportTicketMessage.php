<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketMessage extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return $this->image_path;
    }

    protected static function booted(): void
    {
        static::deleting(function (SupportTicketMessage $message) {
            if ($message->image_path && Storage::exists($message->image_path)) {
                Storage::delete($message->image_path);
            }
        });
    }
}
