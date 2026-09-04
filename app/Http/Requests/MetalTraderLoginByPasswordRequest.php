<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderLoginByPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required','string','regex:/^9\d{9}$/'],
            'password' => ['required','string'],
        ];
    }

    public function messages()
    {
        return [
            'phone.required' => 'شماره موبایل الزامی است',
            'phone.regex' => 'فرمت شماره موبایل وارد شده معتبر نیست',
            'phone.string' => 'فرمت شماره موبایل وارد شده معتبر نیست',
            'password.string' => 'فرمت کلمه عبور وارد شده معتبر نیست',
            'password.required' => 'کلمه عبور الزامی است',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
