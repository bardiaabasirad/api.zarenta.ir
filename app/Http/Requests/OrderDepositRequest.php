<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

class OrderDepositRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount'        => ['required','numeric'],
            'pay_by'        => ['required'],
            'tracking_code' => ['sometimes','nullable'],
            'description'   => ['sometimes','nullable']
        ];
    }

    public function messages()
    {
        return [
            'amount.required' => 'فیلد مبلغ الزامی است',
            'amount.numeric' => 'فیلد مبلغ باید شامل عدد باشد',
            'pay_by.required' => 'فیلد روش پرداخت الزامی است',
        ];
    }
}
