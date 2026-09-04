<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $fillable = [
        'title',
        'content',
        'notification_type',
        'target_admin_id',
        'is_globally_read',
        'created_by',
    ];

    protected $casts = [
        'is_globally_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function targetAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'target_admin_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function readByAdmins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'notification_reads', 'notification_id', 'admin_id')
            ->withPivot('read_at');
    }

    public function scopePublicSingleRead($query)
    {
        return $query->where('notification_type', 'public_single_read');
    }

    public function scopePublicMultiRead($query)
    {
        return $query->where('notification_type', 'public_multi_read');
    }

    public function scopePersonal($query)
    {
        return $query->where('notification_type', 'personal');
    }

    public function scopeUnreadGlobally($query)
    {
        return $query->where('is_globally_read', false);
    }

    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where(function ($q) use ($adminId) {
            // 1. public_single_read که هنوز کسی نخوانده
            $q->where(function ($subQ) {
                $subQ->where('notification_type', 'public_single_read')
                    ->where('is_globally_read', false);
            })
                // 2. public_multi_read که این ادمین نخوانده
                ->orWhere(function ($subQ) use ($adminId) {
                    $subQ->where('notification_type', 'public_multi_read')
                        ->whereDoesntHave('reads', function ($readQ) use ($adminId) {
                            $readQ->where('admin_id', $adminId);
                        });
                })
                // 3. personal های این ادمین که نخوانده
                ->orWhere(function ($subQ) use ($adminId) {
                    $subQ->where('notification_type', 'personal')
                        ->where('target_admin_id', $adminId)
                        ->whereDoesntHave('reads', function ($readQ) use ($adminId) {
                            $readQ->where('admin_id', $adminId);
                        });
                });
        });
    }

    public function isReadByAdmin(int $adminId): bool
    {
        if ($this->notification_type === 'public_single_read') {
            return $this->is_globally_read;
        }

        return $this->reads()->where('admin_id', $adminId)->exists();
    }

    public function markAsReadBy(int $adminId, bool $broadcast = true): void
    {
        \DB::transaction(function () use ($adminId) {
            \App\Models\NotificationRead::updateOrCreate(
                [
                    'notification_id' => $this->id,
                    'admin_id' => $adminId,
                ],
                [
                    'read_at' => now(),
                ]
            );

            if ($this->notification_type === 'public_single_read') {
                $this->update(['is_globally_read' => true]);
            }

            $this->refresh();
        });

        // پخش Event فقط در صورت درخواست
        if ($broadcast) {
            event(new \App\Events\NotificationRead($this, $adminId));
        }
    }

    public function canBeReadBy(int $adminId): bool
    {
        if (in_array($this->notification_type, ['public_single_read', 'public_multi_read'])) {
            return true;
        }

        return $this->notification_type === 'personal' && $this->target_admin_id === $adminId;
    }
}
