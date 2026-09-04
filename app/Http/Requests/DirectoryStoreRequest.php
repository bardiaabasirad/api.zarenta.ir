<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class DirectoryStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slug'  => ['required','max:255', 'unique:directories,slug'],
            'title' => ['required','max:255'],
            'description' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'slug.required' => 'فیلد آدرس یکتای فهرست الزامی است',
            'slug.max' => 'فیلد آدرس یکتای فهرست نباید بیشتر از ۲۵۵ حرف باشد',
            'slug.unique' => 'آدرس یکتای فهرست قبلا انتخاب شده است',
            'title.required' => 'فیلد عنوان فهرست اجباری است',
            'title.max' => 'فیلد عنوان فهرست نباید بیشتر از ۲۵۵ حرف باشد',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->slug),
        ]);
    }
}
