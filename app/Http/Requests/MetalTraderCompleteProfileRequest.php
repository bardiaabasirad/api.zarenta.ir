<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderCompleteProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^9\d{9}$/'],
            'full_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'in:gold_dealer,trader,other'],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'شماره تلفن',
            'full_name' => 'نام و نام خانوادگی',
            'business_type' => 'زمینه فعالیت',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
