<?php

namespace App\Http\Requests;

use App\Enums\IncreaseMethods;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class IncreaseGoldBalanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required','exists:users,id'],
            'amount' => ['required','numeric','regex:/^\d{1,5}(\.\d{1,3})?$/'],
            'increase_reason' => ['required','max:255'],
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
