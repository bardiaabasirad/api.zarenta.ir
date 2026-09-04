<?php

namespace App\Http\Requests;

use App\Enums\IncreaseMethods;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class IncreaseAccountBalanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required','exists:users,id'],
            'amount' => ['required','numeric','digits_between:1,12'],
            'increase_method' => ['required','in:' . implode(',', IncreaseMethods::classConstants())],
            'description' => ['nullable','max:255'],
        ];
    }

//    public function messages()
//    {
//        return [
//            'user_id.required' => 'شناسه کاربر ضروری است',
//            'user_id.exists' => 'شناسه کاربر نامعتبر است',
//            'amount.required' => 'مبلغ افزایش ضروری است',
//            'amount.numeric' => 'مبلغ افزایش باید عددی باشد',
//            'amount.digits_between' => 'مبلغ افزایش می‌تواند',
//        ];
//    }
}
