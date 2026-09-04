<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CategoryStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slug'  => ['required', 'max:255', 'unique:categories,slug'],
            'title' => ['required', 'max:255'],
            'image' => [
                'required',
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
            'slug.required'       => 'فیلد آدرس یکتای دسته‌بندی الزامی است',
            'slug.max'            => 'فیلد آدرس یکتای دسته‌بندی نباید بیشتر از ۲۵۵ حرف باشد',
            'slug.unique'         => 'آدرس یکتای دسته‌بندی قبلا انتخاب شده است',
            'title.required'      => 'فیلد عنوان دسته‌بندی اجباری است',
            'title.max'           => 'فیلد عنوان دسته‌بندی نباید بیشتر از ۲۵۵ حرف باشد',
            'images.image'        => 'فایل انتخاب شده باید یک تصویر باشد',
            'images.dimensions'   => 'ابعاد تصویر نباید بزرگتر از ۱۰۲۴×۱۰۲۴ باشد',
            'images.max'          => 'حجم تصویر باید کمتر از ۵ مگابایت باشد',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->slug),
        ]);
    }
}
