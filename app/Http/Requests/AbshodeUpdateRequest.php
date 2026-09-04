<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AbshodeUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reference_channel_id'      => ['sometimes', 'required', 'exists:reference_channels,id'],
            'common_fixed_price_sell'   => ['sometimes', 'required', 'integer', 'min:0'],
            'common_fixed_price_buy'    => ['sometimes', 'required', 'integer', 'min:0'],
            'today_fixed_price_sell'    => ['sometimes', 'required', 'integer', 'min:0'],
            'today_fixed_price_buy'     => ['sometimes', 'required', 'integer', 'min:0'],
            'tomorrow_fixed_price_sell' => ['sometimes', 'required', 'integer', 'min:0'],
            'tomorrow_fixed_price_buy'  => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    public function messages()
    {
        return [
            'reference_channel_id.required'     => 'شناسه کانال مرجع ضروری است.',
            'reference_channel_id.exists'       => 'شناسه کانال مرجع نامعتبر است.',
            'common_fixed_price_sell.integer'   => 'این فیلد باید نوع داده عددی باشد.',
            'common_fixed_price_sell.min'       => 'این فیلد نباید کوچکتر از :min باشد.',
            'common_fixed_price_buy.integer'    => 'این فیلد باید نوع داده عددی باشد.',
            'common_fixed_price_buy.min'        => 'این فیلد نباید کوچکتر از :min باشد.',
            'today_fixed_price_sell.integer'    => 'این فیلد باید نوع داده عددی باشد.',
            'today_fixed_price_sell.min'        => 'این فیلد نباید کوچکتر از :min باشد.',
            'today_fixed_price_buy.integer'     => 'این فیلد باید نوع داده عددی باشد.',
            'today_fixed_price_buy.min'         => 'این فیلد نباید کوچکتر از :min باشد.',
            'tomorrow_fixed_price_sell.integer' => 'این فیلد باید نوع داده عددی باشد.',
            'tomorrow_fixed_price_sell.min'     => 'این فیلد نباید کوچکتر از :min باشد.',
            'tomorrow_fixed_price_buy.integer'  => 'این فیلد باید نوع داده عددی باشد.',
            'tomorrow_fixed_price_buy.min'      => 'این فیلد نباید کوچکتر از :min باشد.',
        ];
    }
}
