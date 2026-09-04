<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::updated(function () {
            Cache::forget('contact_info');
        });
    }

    public static function getMultiple(array $keys): Collection
    {
        return static::whereIn('option_key', $keys)
            ->get()
            ->keyBy('option_key');
    }

    protected $guarded = [
        'id', 'option_name'
    ];

    protected static function booted()
    {
        static::saved(function ($setting) {
            if ($setting->option_key === 'default_metal_trader_group_id') {
                Cache::forget('setting.default_metal_trader_group_id');
            } elseif ($setting->option_key === 'molten_page_metal_item_id') {
                Cache::forget('setting.molten_page_metal_item_id');
            } elseif ($setting->option_key === 'balance_metal_item_id') {
                Cache::forget('setting.balance_metal_item_id');
            }
        });
    }
}
