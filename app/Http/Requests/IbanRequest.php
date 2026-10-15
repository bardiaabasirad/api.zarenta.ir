<?php

namespace App\Http\Requests;

use App\Rules\IranianBankCardOrSheba;
use Illuminate\Foundation\Http\FormRequest;

class IbanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'iban' => ['required', new IranianBankCardOrSheba()],
        ];
    }

    public function messages()
    {
        return [
            'iban.required' => 'شماره شبا ضروری است'
        ];
    }
}
