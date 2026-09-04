<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderVerifyPhoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required','string','regex:/^9\d{9}$/'],
            'verification_code' => 'required|string|digits_between:4,8',
            'action' => ['sometimes', 'string', 'in:login,forgot_password,otp'],
        ];
    }

    public function messages()
    {
        return [
            'phone.required' => 'شماره موبایل الزامی است',
            'phone.regex' => 'فرمت شماره موبایل وارد شده معتبر نیست',
            'phone.string' => 'فرمت شماره موبایل وارد شده معتبر نیست',
            'verification_code.required' => 'کد تایید ضروری است.',
            'verification_code.digits_between' => 'کد تایید باید یک عدد ۶ رقمی باشد.',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
