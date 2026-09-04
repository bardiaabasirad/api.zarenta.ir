<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'size_unit_id'  => ['nullable','sometimes','exists:size_units,id'],
            'title'         => ['sometimes','required','max:255'],
            'description'   => ['sometimes'],
            'gold_carat'    => ['sometimes','required','integer','between:0,999'],
            'status'        => ['sometimes','in:' . implode(',', ProductStatus::classConstants())],
            'credit_payment'=> ['sometimes','in:active,inactive'],
            'vat'           => ['sometimes','in:active,inactive'],
            'categories'    => ['sometimes','array'],
            'categories.*'  => ['sometimes','exists:categories,id'],
            'directories'   => ['sometimes','array'],
            'directories.*' => ['sometimes','exists:directories,id'],
            'images'        => ['sometimes','array'],
            'images.*'      => ['image', 'dimensions:max_width=1024,max_height=1024','max:5120'],
            'removed_images'=> ['sometimes','array'],
        ];
    }

    public function messages()
    {
        return [
            'gold_carat.required' => 'فیلد عیار طلا الزامی است',
            'gold_carat.integer'  => 'فیلد عیار طلا باید عدد باشد',
            'gold_carat.between'  => 'فیلد عیار طلا باید بین ۰ تا ۹۹۹ باشد',
            'images.array'        => 'تصاویر انتخاب شده باید بصورت آرایه باشند',
            'images.*.image'      => 'فایل انتخاب شده باید یک تصویر باشد',
            'images.*.dimensions' => 'ابعاد هر تصویر نباید بزرگتر از ۱۰۲۴×۱۰۲۴ باشد',
            'images.*.max'        => 'حجم هر تصویر باید کمتر از ۵ مگابایت باشد',
        ];
    }
}
