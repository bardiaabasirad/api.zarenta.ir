<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^9\d{9}$/',
            ],

            'verification_code' => [
                'required',
                'string',
                'digits:6',
            ],

            'items' => [
                'sometimes',
                'array',
            ],

            'items.*.product_id' => [
                'required_with:items',
                'integer',
                'min:1',
                'exists:products,id',
            ],

            'items.*.variety_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:varieties,id',
            ],

            'items.*.count' => [
                'required_with:items',
                'integer',
                'min:1',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'شماره موبایل',
            'verification_code' => 'کد تأیید',
            'items' => 'اقلام سبد خرید',
            'items.*.product_id' => 'شناسه محصول',
            'items.*.variety_id' => 'شناسه تنوع محصول',
            'items.*.count' => 'تعداد کالا',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge([
                'phone' => getPhone($this->input('phone')),
            ]);
        }
    }
}
