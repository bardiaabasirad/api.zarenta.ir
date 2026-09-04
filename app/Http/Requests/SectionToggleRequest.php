<?php

namespace App\Http\Requests;

use App\Enums\SectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionToggleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes','in:' . implode(',', SectionStatus::classConstants())],
        ];
    }
}
