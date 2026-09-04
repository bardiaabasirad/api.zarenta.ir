<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required','string','regex:/^9\d{9}$/'],
            'force_otp' => ['sometimes', 'boolean'],
            'action' => ['sometimes', 'string', 'in:forgot_password,otp'],
        ];
    }

    public function messages()
    {
        return [
            'phone.required' => 'شماره موبایل الزامی است',
            'phone.regex' => 'فرمت شماره موبایل وارد شده معتبر نیست',
            'phone.string' => 'فرمت شماره موبایل وارد شده معتبر نیست',
        ];
    }

    protected function prepareForValidation()
    {
        // Modify the 'phone' value if it's present in the request
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
