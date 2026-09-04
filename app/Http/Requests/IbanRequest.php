<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IbanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'iban' => ['required','iban_or_card'],
        ];
    }

    public function messages()
    {
        return [
            'iban.required' => 'شماره شبا ضروری است',
            'iban.iban_or_card' => 'شماره شبا‌ یا کارت اشتباه است',
        ];
    }
}
