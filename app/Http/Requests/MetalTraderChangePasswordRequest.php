<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class MetalTraderChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required','exists:metal_traders,phone','regex:/^9\d{9}$/'],
            'password' => [
                'required',
                'confirmed', // چک می‌کند که با فیلد password_confirmation یکسان باشد
                Password::min(8) // حداقل ۸ کاراکتر
                //                ->letters()    // شامل حروف
                //                ->mixedCase()  // حروف کوچک و بزرگ
                //                ->numbers()    // اعداد
                //                ->symbols()    // کاراکترهای خاص
            ],
        ];
    }

    public function messages()
    {
        return [
            'password.confirmed' => 'تکرار رمز عبور با خود رمز عبور مطابقت ندارد.',
            'password.required' => 'وارد کردن رمز عبور جدید الزامی است.',
            'phone.required' => 'شماره تلفن ضروری است.',
            'phone.regex' => 'شماره تلفن وارد شده معتبر نمی‌باشد.',
        ];
    }

    protected function prepareForValidation()
    {
        // Modify the 'phone' value if it's present in the request
        if ($this->has('phone')) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
