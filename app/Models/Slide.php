<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;


class Slide extends Model
{
    protected $fillable = [
        'slider_id',
        'title',
        'subtitle',
        'image_path_desktop',
        'image_path_tablet',
        'image_path_mobile',
        'link_url',
        'action_type',
        'action_value',
        'action_text',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'slider_id'  => 'integer',
    ];

    // اضافه کردن خودکار URL کامل عکس به خروجی JSON
    protected $appends = ['image_url'];

    public function slider(): BelongsTo
    {
        return $this->belongsTo(Slider::class);
    }

    // Accessor برای ساخت لینک مستقیم فایل
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }
}
