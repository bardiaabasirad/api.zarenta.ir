<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\Relation;

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

    protected function authenticatableType(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => Relation::getMorphAlias($value),
        );
    }

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
