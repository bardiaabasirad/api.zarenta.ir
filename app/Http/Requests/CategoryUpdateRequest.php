<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class CategoryUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'max:255'],
            'image' => [
                'sometimes',
                'file', // Use 'file' instead of 'image' to allow SVGs
                'mimes:jpeg,jpg,png,svg',
                function ($attribute, $value, $fail) {
                    // Apply dimensions only for non-SVG files
                    if ($value->getClientOriginalExtension() !== 'svg') {
                        $image = getimagesize($value);
                        if ($image[0] > 1024 || $image[1] > 1024) {
                            $fail('The ' . $attribute . ' dimensions must not exceed 1024x1024 pixels.');
                        }
                    }
                },
                'max:5120'
            ],
        ];
    }

    public function messages()
    {
        return [
            'title.required'      => 'فیلد عنوان دسته‌بندی الزامی است',
            'title.max'           => 'فیلد عنوان دسته‌بندی نباید بیشتر از ۲۵۵ حرف باشد',
            'images.image'        => 'فایل انتخاب شده باید یک تصویر باشد',
            'images.dimensions'   => 'ابعاد هر تصویر نباید بزرگتر از ۱۰۲۴×۱۰۲۴ باشد',
            'images.max'          => 'حجم هر تصویر باید کمتر از ۵ مگابایت باشد',
        ];
    }
}
