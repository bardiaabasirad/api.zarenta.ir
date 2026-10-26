<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Unit extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'multiplier_to_gram',
        'is_weight',
    ];

    protected $casts = [
        'multiplier_to_gram' => 'string',
        'is_weight' => 'boolean',
    ];

    public static function cached(string $id): ?self
    {
        return static::allCached()->firstWhere('id', $id);
    }

    public static function allCached()
    {
        return Cache::rememberForever('units:all', function () {
            return static::query()->orderBy('id')->get();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('units:all'));
        static::deleted(fn () => Cache::forget('units:all'));
    }
}
