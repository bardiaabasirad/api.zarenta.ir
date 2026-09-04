<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ActiveInactiveCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): bool
    {
        return $value === 'active';
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return $value ? 'active' : 'inactive';
    }
}

