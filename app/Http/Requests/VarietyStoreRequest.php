<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VarietyStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id'            => ['required', 'exists:products,id'],
            'color_id'              => ['nullable', 'exists:colors,id'],
            'weight'                => ['required', 'numeric', 'between:0.001,999.999'],
            'count'                 => ['required', 'numeric', 'between:0,255'],
            'size'                  => ['nullable', 'numeric', 'between:0,99999'],
            'percentage_buy_wage'   => ['nullable', 'decimal:0,2'],
            'tomans_buy_wage'       => ['nullable', 'integer','between:0,999999999'],
            'percentage_sell_wage'  => ['nullable', 'decimal:0,2'],
            'tomans_sell_wage'      => ['nullable', 'integer','between:0,999999999'],
            'percentage_profit'     => ['nullable', 'decimal:0,2'],
            'tomans_profit'         => ['nullable', 'integer','between:0,999999999'],
            'percentage_discount'   => ['nullable', 'decimal:0,2'],
            'tomans_discount'       => ['nullable', 'integer','between:0,999999999'],
            'images'                => ['nullable', 'array'],
            'images.*'              => ['sometimes', 'exists:product_images,id'],
        ];
    }

    public function messages()
    {
        return [
            'color_id.exists' => 'رنگ انتخاب شده معتبر نیست',
            'weight.required' => 'فیلد وزن الزامی است',
            'weight.numeric' => 'فیلد وزن باید شامل عدد باشد',
            'weight.between' => 'فیلد وزن باید بین ۰.۰۰۱ و ۹۹۹.۹۹۹ باشد',
            'count.required' => 'فیلد تعداد الزامی است',
            'count.numeric' => 'فیلد تعداد باید شامل عدد باشد',
            'count.between' => 'فیلد تعداد باید بین ۰ و ۲۵۵ باشد',
            'size.numeric' => 'فیلد سایز باید شامل عدد باشد',
            'size.between' => 'فیلد سایز باید بین ۰ و ۹۹۹۹۹ باشد',
            'percentage_buy_wage.decimal' => 'فیلد درصد اجرت خرید باید بین ۰ تا ۹۹ باشد',
            'tomans_buy_wage.integer' => 'فیلد اجرت خرید باید شامل عدد باشد',
            'tomans_buy_wage.between' => 'فیلد اجرت خرید باید بین ۰ و ۹۹۹۹۹۹۹۹۹ باشد',
            'percentage_sell_wage.decimal' => 'فیلد درصد اجرت فروش باید بین ۰ تا ۹۹ باشد',
            'tomans_sell_wage.integer' => 'فیلد اجرت فروش باید شامل عدد باشد',
            'tomans_sell_wage.between' => 'فیلد اجرت خرید باید بین ۰ و ۹۹۹۹۹۹۹۹۹ باشد',
            'percentage_profit.decimal' => 'فیلد درصد حاشیه سود باید بین ۰ تا ۹۹ باشد',
            'tomans_profit.integer' => 'فیلد حاشیه سود باید شامل عدد باشد',
            'tomans_profit.between' => 'فیلد حاشیه سود باید بین ۰ و ۹۹۹۹۹۹۹۹۹ باشد',
            'percentage_discount.decimal' => 'فیلد درصد تخفیف باید بین ۰ تا ۹۹ باشد',
            'tomans_discount.integer' => 'فیلد تخفیف باید شامل عدد باشد',
            'tomans_discount.between' => 'فیلد تخفیف باید بین ۰ و ۹۹۹۹۹۹۹۹۹ باشد',
            'images.array' => 'تصاویر انتخاب شده باید بصورت آرایه باشد',
            'images.*.exists' => 'تصویر انتخاب شده معتبر نیست',
        ];
    }
}
