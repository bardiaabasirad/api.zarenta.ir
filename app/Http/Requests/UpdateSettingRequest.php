<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'option_value' => ['required'],
        ];
    }
}
