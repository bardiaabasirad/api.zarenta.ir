<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetalOrderLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'new_values' => 'array',
        'old_values' => 'array',
    ];

    public function newValues(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $array = json_decode($value, true);

                if (isset($array['extra_data']) && is_string($array['extra_data'])) {
                    $decodedDescription = json_decode($array['extra_data'], true);
                    $array['extra_data'] = $decodedDescription ?? $array['extra_data'];
                }

                return $array;
            },
        );
    }

    public function oldValues(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $array = json_decode($value, true);

                if (isset($array['extra_data']) && is_string($array['extra_data'])) {
                    $decodedDescription = json_decode($array['extra_data'], true);
                    $array['extra_data'] = $decodedDescription ?? $array['extra_data'];
                }

                return $array;
            },
        );
    }


    public function loggable()
    {
        return $this->morphTo();
    }
}
