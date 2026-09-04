<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class VarietyUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'count'                 => ['required_with:cause','numeric','gte:0'],
            'cause'                 => ['required_with:count'],
            'color_id'              => ['sometimes','nullable','exists:colors,id'],
            'weight'                => ['sometimes','nullable','numeric','gte:0'],
            'size'                  => ['sometimes','nullable','numeric','gte:0'],
            'percentage_buy_wage'   => ['sometimes','nullable','decimal:0,2'],
            'tomans_buy_wage'       => ['sometimes','nullable','integer','between:0,999999999'],
            'percentage_sell_wage'  => ['sometimes','nullable','decimal:0,2'],
            'tomans_sell_wage'      => ['sometimes','nullable','integer','between:0,999999999'],
            'percentage_profit'     => ['nullable','decimal:0,2'],
            'tomans_profit'         => ['sometimes','nullable','integer','between:0,999999999'],
            'percentage_discount'   => ['sometimes','nullable','decimal:0,2'],
            'tomans_discount'       => ['sometimes','nullable','integer','between:0,999999999'],
            'images'                => ['nullable', 'array'],
            'images.*'              => ['sometimes','exists:product_images,id'],
        ];
    }
}
