<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    public const MODE_IMAGE_LIST = 'image_list';
    public const MODE_BANNER = 'banner';

    public const SIZE_OPTIONS = [
        'size-10', 'size-12', 'size-14', 'size-16', 'size-20', 'size-24',
        'size-28', 'size-32', 'size-36', 'size-40', 'size-44', 'size-48',
        'size-52', 'size-56', 'size-60', 'size-64', 'size-72', 'size-80', 'size-96',
    ];

    public const DEFAULT_LAYOUT = [
        'mode' => self::MODE_IMAGE_LIST,
        'show_title' => true,
        'show_image_text' => true,
        'rounded' => 'rounded-lg',
        'sizes' => [
            'sm' => 'size-16',
            'md' => 'size-20',
            'lg' => 'size-24',
        ],
        'padding_x' => [
            'sm' => 'px-4',
            'md' => 'px-4',
            'lg' => 'px-4',
        ],
    ];

    protected $fillable = ['title', 'images', 'layout'];

    protected $casts = [
        'images' => 'json',
    ];

    public function sections()
    {
        return $this->morphMany(Section::class, 'sectionable');
    }

    protected function layout(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                $merged = array_replace_recursive(
                    self::DEFAULT_LAYOUT,
                    is_array($decoded) ? $decoded : []
                );

                unset($merged['columns']);

                if (!in_array($merged['mode'] ?? '', [self::MODE_IMAGE_LIST, self::MODE_BANNER], true)) {
                    $merged['mode'] = self::MODE_IMAGE_LIST;
                }

                return $merged;
            },
            set: function ($value) {
                $payload = is_array($value) ? $value : self::DEFAULT_LAYOUT;
                unset($payload['columns']);

                return json_encode($payload, JSON_UNESCAPED_UNICODE);
            },
        );
    }
}
