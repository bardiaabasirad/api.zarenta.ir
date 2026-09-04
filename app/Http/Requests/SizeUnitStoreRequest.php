<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class SizeUnitStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'unit' => ['required','max:255'],
        ];
    }

    public function messages()
    {
        return [
            'unit.required' => 'واحد سایز ضروری است',
            'unit.max'      => 'حداکثر طول واحد سایز ۲۵۵ کاراکتر می‌باشد',
        ];
    }
}
