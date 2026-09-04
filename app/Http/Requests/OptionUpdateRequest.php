<?php

namespace App\Http\Requests;

use App\Enums\Option;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class OptionUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required','max:255'],
        ];
    }
}
