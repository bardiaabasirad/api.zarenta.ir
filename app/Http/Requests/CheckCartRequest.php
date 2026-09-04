<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*' => ['bail', 'required', 'array'],

            'items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'items.*.variety_id' => ['bail', 'required', 'integer', 'exists:varieties,id'],
            'items.*.count' => ['bail', 'required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'آیتم‌های سبد خرید',
            'items.*.product_id' => 'محصول',
            'items.*.variety_id' => 'تنوع',
            'items.*.count' => 'تعداد',
        ];
    }
}
