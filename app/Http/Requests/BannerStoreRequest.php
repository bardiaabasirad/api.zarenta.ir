<?php

namespace App\Http\Requests;

use App\Enums\SectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BannerStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'        => ['required', 'max:255'],
            'target'       => ['required', 'in:_blank,_self'],
            'image'        => ['required', 'image', 'dimensions:max_width=1280,max_height=720','max:5120'],
            'action'       => ['required', 'in:open_product,open_category,open_directory,open_link'],
            'actionable'   => [
                'required',
                Rule::when($this->action === 'open_product', ['exists:products,id']),
                Rule::when($this->action === 'open_category', ['exists:categories,id']),
                Rule::when($this->action === 'open_directory', ['exists:directories,id']),
                Rule::when($this->action === 'open_link', ['url']),
            ],
        ];
    }

    public function messages()
    {
        $messages = [
            'title.required' => 'عنوان تصویر الزامی است',
            'title.max' => 'عنوان تصویر نباید بیشتر از ۲۵۵ حرف باشد',
            'target.required' => 'فیلد نحوه باز شدن لینک الزامی است',
            'target.in' => 'نحوه باز شدن لینک انتخاب شده معتبر نیست',
            'image.required' => 'تصویر بنر الزامی است',
            'image.image' => 'تصویر انتخاب شده باید یک تصویر با پسوندهای متداول باشد',
            'image.dimensions' => 'ابعاد تصویر باید بین ۱۲۸۰ در ۷۲۰ پیکسل باشد ',
            'image.max' => 'حجم تصویر باید کمتر از ۵ مگابایت باشد',
            'action.required' => 'عملیات بنر الزامی است',
            'actionable.required' => 'این فیلد الزامی است',
        ];

        return $messages;
    }
}
