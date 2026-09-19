<?php

namespace App\Http\Requests;

use App\Models\Slider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // دریافت مدل Slider از پارامتر Route
        $slider = $this->route('slider');

        $sliderId = $slider instanceof Slider
            ? $slider->id
            : $slider;

        return [
            /*
            |--------------------------------------------------------------------------
            | اطلاعات اصلی اسلایدر
            |--------------------------------------------------------------------------
            */
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'key' => [
                'required',
                'string',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('sliders', 'key')->ignore($sliderId),
            ],

            'is_active' => [
                'required',
                'in:0,1,true,false',
            ],

            /*
            |--------------------------------------------------------------------------
            | اسلایدهای حذف‌شده
            |--------------------------------------------------------------------------
            */
            'deleted_slide_ids' => [
                'nullable',
                'array',
            ],

            'deleted_slide_ids.*' => [
                'integer',
                'exists:slides,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | آرایه اسلایدها
            |--------------------------------------------------------------------------
            */
            'slides' => [
                'nullable',
                'array',
            ],

            'slides.*.id' => [
                'nullable',
                'integer',
                'exists:slides,id',
            ],

            'slides.*.title' => [
                'required',
                'string',
                'max:200',
            ],

            'slides.*.subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | تصویر دسکتاپ
            |--------------------------------------------------------------------------
            */
            'slides.*.image_path_desktop' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'slides.*.image_desktop' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp,svg',
                'max:4096',
            ],

            /*
            |--------------------------------------------------------------------------
            | تصویر تبلت
            |--------------------------------------------------------------------------
            */
            'slides.*.image_path_tablet' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'slides.*.image_tablet' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp,svg',
                'max:4096',
            ],

            /*
            |--------------------------------------------------------------------------
            | تصویر موبایل
            |--------------------------------------------------------------------------
            */
            'slides.*.image_path_mobile' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'slides.*.image_mobile' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,webp,svg',
                'max:4096',
            ],

            /*
            |--------------------------------------------------------------------------
            | لینک و عملیات اسلاید
            |--------------------------------------------------------------------------
            */
            'slides.*.link_url' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'slides.*.action_type' => [
                'required',
                'string',
                'in:none,link,route,product',
            ],

            'slides.*.action_value' => [
                'nullable',
                'string',
                'max:255',
            ],

            'slides.*.action_text' => [
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | ترتیب و وضعیت اسلاید
            |--------------------------------------------------------------------------
            */
            'slides.*.sort_order' => [
                'required',
                'integer',
                'min:0',
            ],

            'slides.*.is_active' => [
                'required',
                'in:0,1,true,false',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام اسلایدر الزامی است.',
            'key.required' => 'کلید اسلایدر الزامی است.',
            'key.regex' => 'کلید اسلایدر فقط می‌تواند شامل حروف انگلیسی کوچک، اعداد و زیرخط باشد.',
            'key.unique' => 'این کلید اختصاصی قبلاً برای اسلایدر دیگری ثبت شده است.',

            'slides.*.title.required' => 'عنوان اسلاید الزامی است.',
            'slides.*.title.max' => 'عنوان اسلاید نمی‌تواند بیشتر از ۲۰۰ کاراکتر باشد.',

            'slides.*.image_desktop.file' => 'فایل تصویر دسکتاپ معتبر نیست.',
            'slides.*.image_desktop.image' => 'تصویر دسکتاپ باید از نوع تصویر معتبر باشد.',
            'slides.*.image_desktop.mimes' => 'فرمت تصویر دسکتاپ باید jpeg، png، jpg، webp یا svg باشد.',
            'slides.*.image_desktop.max' => 'حجم تصویر دسکتاپ نمی‌تواند بیشتر از ۴ مگابایت باشد.',

            'slides.*.image_tablet.file' => 'فایل تصویر تبلت معتبر نیست.',
            'slides.*.image_tablet.image' => 'تصویر تبلت باید از نوع تصویر معتبر باشد.',
            'slides.*.image_tablet.mimes' => 'فرمت تصویر تبلت باید jpeg، png، jpg، webp یا svg باشد.',
            'slides.*.image_tablet.max' => 'حجم تصویر تبلت نمی‌تواند بیشتر از ۴ مگابایت باشد.',

            'slides.*.image_mobile.file' => 'فایل تصویر موبایل معتبر نیست.',
            'slides.*.image_mobile.image' => 'تصویر موبایل باید از نوع تصویر معتبر باشد.',
            'slides.*.image_mobile.mimes' => 'فرمت تصویر موبایل باید jpeg، png، jpg، webp یا svg باشد.',
            'slides.*.image_mobile.max' => 'حجم تصویر موبایل نمی‌تواند بیشتر از ۴ مگابایت باشد.',

            'slides.*.action_type.in' => 'نوع عملیات انتخاب‌شده برای اسلاید معتبر نیست.',
            'slides.*.sort_order.integer' => 'ترتیب نمایش باید عددی باشد.',
            'slides.*.sort_order.min' => 'ترتیب نمایش نمی‌تواند منفی باشد.',
        ];
    }
}
