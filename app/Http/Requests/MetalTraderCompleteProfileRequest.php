<?php

namespace App\Http\Requests;

use App\Models\MetalTrader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetalTraderCompleteProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $traderId = MetalTrader::where('phone', $this->input('phone'))->value('id');

        return [
            'phone' => ['required', 'string', 'regex:/^9\d{9}$/'],
            'full_name' => ['required', 'string', 'max:255'],
            'national_code' => [
                'required',
                'string',
                'digits:10',
                Rule::unique('metal_traders', 'national_code')->ignore($traderId),
            ],
            'address' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'شماره تلفن',
            'full_name' => 'نام و نام خانوادگی',
            'national_code' => 'کد ملی',
            'address' => 'آدرس',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
