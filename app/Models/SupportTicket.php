<?php

namespace App\Models;

use App\Constants\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupportTicket extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_message_at' => 'datetime',
        'rated_at' => 'datetime',
        'closed_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('id');
    }

    public function operators(): HasMany
    {
        return $this->hasMany(SupportTicketOperator::class);
    }

    public function lastRepliedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_replied_admin_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, SupportTicketStatus::openStatuses(), true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, SupportTicketStatus::closedStatuses(), true);
    }
}
