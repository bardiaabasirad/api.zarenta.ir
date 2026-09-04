<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^9\d{9}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'شماره موبایل الزامی است',
            'phone.string' => 'فرمت شماره موبایل وارد شده معتبر نیست',
            'phone.regex' => 'فرمت شماره موبایل وارد شده معتبر نیست',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
