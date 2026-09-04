<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class MetalTraderChangePasswordByProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
//            'current_password' => [
//                Rule::requiredIf(fn () => filled($this->user()->password)),
//                'nullable',
//                'string',
//            ],
            'password' => [
                'required',
                'string',
                'confirmed',
//                'different:current_password',
                Password::min(8),
            ],
        ];
    }

//    public function withValidator(Validator $validator): void
//    {
//        $validator->after(function (Validator $validator) {
//            // اگر قبلا خطای required ثبت شده، خطای تکراری اضافه نکن
//            if ($validator->errors()->has('current_password')) {
//                return;
//            }
//
//            $user = $this->user();
//
//            // کاربر رمزی ندارد؛ نیازی به بررسی نیست
//            if (blank($user->password)) {
//                return;
//            }
//
//            if (! Hash::check((string) $this->input('current_password'), $user->password)) {
//                $validator->errors()->add('current_password', 'رمز فعلی وارد شده صحیح نیست.');
//            }
//        });
//    }

    public function attributes(): array
    {
        return [
//            'current_password' => 'رمز فعلی',
            'password' => 'رمز جدید',
        ];
    }

    public function messages(): array
    {
        return [
//            'current_password.required' => 'برای تغییر رمز، وارد کردن رمز فعلی الزامی است.',
            'password.confirmed' => 'رمز جدید و تکرار آن یکسان نیستند.',
//            'password.different' => 'رمز جدید نباید با رمز فعلی یکسان باشد.',
        ];
    }
}
