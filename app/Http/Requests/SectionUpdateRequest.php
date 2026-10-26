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
            'banner.layout.mode'            => ['sometimes', 'string', Rule::in(['image_list', 'banner'])],
            'banner.layout.show_title'      => ['sometimes', 'boolean'],
            'banner.layout.show_image_text' => ['sometimes', 'boolean'],
            'banner.layout.rounded'         => ['sometimes', 'string', Rule::in([
                'rounded-none', 'rounded-sm', 'rounded', 'rounded-md', 'rounded-lg',
                'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-full',
            ])],
            'banner.layout.sizes.sm'        => ['sometimes', 'string', Rule::in([
                'size-10', 'size-12', 'size-14', 'size-16', 'size-20', 'size-24',
                'size-28', 'size-32', 'size-36', 'size-40', 'size-44', 'size-48',
                'size-52', 'size-56', 'size-60', 'size-64', 'size-72', 'size-80', 'size-96',
            ])],
            'banner.layout.sizes.md'        => ['sometimes', 'string', Rule::in([
                'size-10', 'size-12', 'size-14', 'size-16', 'size-20', 'size-24',
                'size-28', 'size-32', 'size-36', 'size-40', 'size-44', 'size-48',
                'size-52', 'size-56', 'size-60', 'size-64', 'size-72', 'size-80', 'size-96',
            ])],
            'banner.layout.sizes.lg'        => ['sometimes', 'string', Rule::in([
                'size-10', 'size-12', 'size-14', 'size-16', 'size-20', 'size-24',
                'size-28', 'size-32', 'size-36', 'size-40', 'size-44', 'size-48',
                'size-52', 'size-56', 'size-60', 'size-64', 'size-72', 'size-80', 'size-96',
            ])],
            'banner.layout.padding_x.sm'    => ['sometimes', 'string', Rule::in(['px-0', 'px-2', 'px-4', 'px-6', 'px-8', 'px-10', 'px-12'])],
            'banner.layout.padding_x.md'    => ['sometimes', 'string', Rule::in(['px-0', 'px-2', 'px-4', 'px-6', 'px-8', 'px-10', 'px-12'])],
            'banner.layout.padding_x.lg'    => ['sometimes', 'string', Rule::in(['px-0', 'px-2', 'px-4', 'px-6', 'px-8', 'px-10', 'px-12'])],
        ];
    }

    public function messages()
    {
        $messages = [
            'sectionable_type.required' => 'نوع آیتم الزامی است',
            'banner.title.required_if' => 'عنوان لیست تصویری الزامی است',
            'banner.images.required_if' => 'تصویر لیست الزامی است',
            'banner.images.*.image.required_if' => 'تصویر لیست الزامی است',
            'banner.images.*.image.dimensions' => 'ابعاد تصویر باید بین ۱۲۸۰ در ۷۲۰ پیکسل باشد ',
            'banner.images.*.image.max' => 'حجم تصویر باید کمتر از ۵ مگابایت باشد',
            'banner.images.*.image.image' => 'تصویر انتخاب شده باید یک تصویر با پسوندهای متداول باشد',
            'banner.images.*.action' => 'عملیات تصویر الزامی است',
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
            case 'App\\Models\\Slider':
                $messages['sectionable_id.required_without'] = 'اسلایدر الزامی است';
                break;
            default:
                $messages['sectionable_id.required_without'] = 'این بخش الزامی است';
                break;
        }

        return $messages;
    }
}
