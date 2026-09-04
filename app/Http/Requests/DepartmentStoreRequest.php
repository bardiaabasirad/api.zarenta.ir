<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255|unique:departments,name,' . $this->route('department')?->id,

            'contacts' => 'sometimes|nullable|array|max:10',

            'contacts.*.name' => 'nullable|string|max:100|min:3',

            'contacts.*.phone' => [
                'sometimes',
                'required',
                'string',
                'distinct',
                'regex:/^0[0-9]{10}$/',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام دپارتمان',
            'contacts.*.name' => 'نام اپراتور',
            'contacts.*.phone' => 'شماره تماس',
        ];
    }
}
