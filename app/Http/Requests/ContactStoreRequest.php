<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150'
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'distinct',
                'regex:/^0[0-9]{10}$/'
            ]
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام',
            'phone' => 'شماره تماس',
        ];
    }
}
