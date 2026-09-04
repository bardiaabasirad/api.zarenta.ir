<?php

namespace App\Http\Requests;

use App\Enums\DirectoryStatus;
use Illuminate\Foundation\Http\FormRequest;

class DirectoryUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'max:255'],
            'status' => ['sometimes','required','in:' . implode(',', DirectoryStatus::classConstants())],
            'description' => ['sometimes', 'nullable'],
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
}
