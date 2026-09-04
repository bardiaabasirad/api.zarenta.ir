<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class ColorStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'color_name'    => ['required','max:255'],
            'hex_code'      => ['required','regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }

    public function messages()
    {
        return [
            'color_name.required' => 'رنگ ضروری است',
            'color_name.max'      => 'حداکثر طول نام رنگ ۲۵۵ کاراکتر می‌باشد',
            'hex_code.required' => 'کد رنگ ضروری است',
            'hex_code.regex'      => 'رنگ وارد شده نامعتبر است',
        ];
    }
}
