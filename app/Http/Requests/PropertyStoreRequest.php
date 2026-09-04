<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class PropertyStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'  => ['required','max:100','unique:properties,title'],
            'values' => ['sometimes','array'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'فیلد ویژگی الزامی است',
            'title.unique' => 'این ویژگی از قبل وجود دارد',
            'title.max' => 'فیلد ویژگی نباید بیشتر از ۱۰۰ کاراکتر باشد',
        ];
    }
}
