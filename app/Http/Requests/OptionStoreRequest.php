<?php

namespace App\Http\Requests;

use App\Enums\Option;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class OptionStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required','max:255'],
            'key' => ['required','in:' . implode(',', Option::classConstants())],
        ];
    }

    public function messages()
    {
        return [
            'value.required' => 'فیلد علت الزامی است',
            'value.max' => 'فیلد علت نباید بیشتر از ۲۵۵ حرف باشد',
        ];
    }
}
