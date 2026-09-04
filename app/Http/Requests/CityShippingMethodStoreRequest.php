<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class CityShippingMethodStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'shipping_cost'  => ['required','integer','between:0,9999999'],
            'shipping_method_id'  => ['required','exists:shipping_methods,id'],
            'city_id'  => ['required_without:province_id','sometimes','exists:cities,id'],
            'province_id'  => ['required_without:city_id','sometimes','exists:provinces,id'],
        ];
    }

    public function messages()
    {
        return [
            'shipping_cost.required' => 'فیلد هزینه ارسال ضروری است',
            'shipping_cost.integer' => 'فیلد هزینه ارسال باید نوع داده‌ای عددی باشد',
            'shipping_cost.between' => 'فیلد هزینه ارسال باید بین :min و :max باشد',
        ];
    }
}
