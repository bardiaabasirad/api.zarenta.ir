<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IbanFromCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'card' => ['required'],
        ];
    }

    public function messages()
    {
        return [
            'card.required' => 'شماره کارت ضروری است',
        ];
    }
}
