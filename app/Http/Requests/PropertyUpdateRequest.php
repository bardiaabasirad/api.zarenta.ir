<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class PropertyUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes','max:255'],
            'values'=> ['sometimes','array'],
        ];
    }
}
