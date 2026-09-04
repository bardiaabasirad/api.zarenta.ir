<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description'                   => ['sometimes'],
            'show_on'                       => ['sometimes', 'required', Rule::in(['all', 'desktop', 'mobile']),],
            'sectionable_type'              => ['sometimes', 'required'],
            'sectionable_id'                => ['sometimes', 'required_without:banner'],
            'banner.title'                  => ['sometimes', 'required_if:sectionable_type,App\\Models\\Banner'],
            'banner'                        => ['sometimes', 'required_without:sectionable_id','array'],
            'banner.images'                 => ['sometimes', 'required_if:sectionable_type,App\\Models\\Banner','array'],
            'banner.images.*.action'        => ['sometimes', 'required_if:sectionable_type,App\\Models\\Banner', 'in:open_product,open_category,open_directory,open_link'],
            'banner.images.*.image'         => ['sometimes', 'image', 'dimensions:max_width=1280,max_height=720','max:5120'],
            'banner.images.*.actionable'    => [
                'sometimes',
                'required_if:sectionable_type,App\\Models\\Banner',
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
            'sectionable_type.required' => 'نوع آیتم الزامی است',
            'banner.title.required_if' => 'عنوان بنر الزامی است',
            'banner.images.required_if' => 'تصویر بنر الزامی است',
            'banner.images.*.image.required_if' => 'تصویر بنر الزامی است',
            'banner.images.*.image.dimensions' => 'ابعاد تصویر باید بین ۱۲۸۰ در ۷۲۰ پیکسل باشد ',
            'banner.images.*.image.max' => 'حجم تصویر باید کمتر از ۵ مگابایت باشد',
            'banner.images.*.image.image' => 'تصویر انتخاب شده باید یک تصویر با پسوندهای متداول باشد',
            'banner.images.*.action' => 'عملیات بنر الزامی است',
            'banner.images.*.actionable' => 'این فیلد الزامی است',
        ];

        switch ($this->sectionable_type) {
            case 'App\\Models\\Widget':
                $messages['sectionable_id.required_without'] = 'ویجت الزامی است';
                break;
            case 'App\\Models\\Category':
                $messages['sectionable_id.required_without'] = 'دسته‌بندی الزامی است';
                break;
            case 'App\\Models\\Directory':
                $messages['sectionable_id.required_without'] = 'فهرست الزامی است';
                break;
            default:
                $messages['sectionable_id.required_without'] = 'این بخش الزامی است';
                break;
        }

        return $messages;
    }
}
