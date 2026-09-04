<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'size_unit_id'  => ['nullable','exists:size_units,id'],
            'title'         => ['required','max:255'],
            'description'   => ['nullable'],
            'gold_carat'    => ['required','integer','between:0,999'],
            'status'        => ['nullable','in:' . implode(',', ProductStatus::classConstants())],
            'vat'           => ['nullable','in:active,inactive'],
            'credit_payment'=> ['nullable','in:active,inactive'],
            'categories'    => ['nullable','array'],
            'categories.*'  => ['nullable','exists:categories,id'],
            'directories'   => ['nullable','array'],
            'directories.*' => ['nullable','exists:directories,id'],
            'images'        => ['nullable','array'],
            'images.*'      => ['image', 'dimensions:max_width=1024,max_height=1024','max:5120'],
        ];
    }

    public function messages()
    {
        return [
            'gold_carat.required'   => 'فیلد عیار طلا الزامی است',
            'images.array'          => 'تصاویر انتخاب شده باید بصورت آرایه باشند',
            'images.*.image'        => 'فایل انتخاب شده باید یک تصویر باشد',
            'images.*.dimensions'   => 'ابعاد تصویر نباید بزرگتر از ۱۰۲۴×۱۰۲۴ باشد',
            'images.*.max'          => 'حجم تصویر باید کمتر از ۵ مگابایت باشد',
            'images.*.uploaded'     => 'تصویر نامعتبر است',
            'size_unit_id.exists'   => 'واحد سایز انتخاب شده نامعتبر است',
        ];
    }
}
