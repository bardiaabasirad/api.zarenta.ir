<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'code',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'verified_at' => 'datetime',
        'failed_attempts' => 'integer',
    ];

    public function scopeNotUsedValid(Builder $query, string $authenticatableType, string $phone, string $code)
    {
        return $query->whereNull('verified_at')
            ->where('phone', $phone)
            ->where('authenticatable_type', $authenticatableType)
            ->where('code', $code)
            ->where('expired_at', '>=', now())
            ->latest();
    }

    public function scopeActive(
        Builder $query,
        string $authenticatableType,
        string $phone
    ): Builder {
        return $query
            ->whereNull('verified_at')
            ->where('phone', $phone)
            ->where('authenticatable_type', $authenticatableType)
            ->where('expired_at', '>=', now());
    }

    public function use(): bool
    {
        return $this->update([
            'verified_at' => now(),
        ]);
    }
}
