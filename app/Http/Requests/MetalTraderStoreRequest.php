<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MetalTraderStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'max:255'],
            'phone' => ['required', 'regex:/^9\d{9}$/', 'unique:metal_traders,phone'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
            'national_code' => ['sometimes', 'nullable', 'string', 'digits:10', 'unique:metal_traders,national_code'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'business_type' => ['sometimes', 'nullable', Rule::in(['gold_dealer', 'trader', 'other'])],
            'asset_risk_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'dealing_group_id' => ['sometimes', 'nullable', 'exists:dealing_groups,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام و نام خانوادگی',
            'phone' => 'شماره موبایل',
            'password' => 'رمز عبور',
            'national_code' => 'کد ملی',
            'address' => 'آدرس',
            'business_type' => 'زمینه فعالیت',
            'asset_risk_threshold' => 'آستانه ریسک نسبت دارایی',
            'dealing_group_id' => 'گروه معاملاتی',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'نام و نام خانوادگی اجباری است',
            'name.max' => 'نام و نام خانوادگی طولانی است',
            'phone.regex' => 'شماره موبایل نامعتبر است',
            'phone.required' => 'شماره موبایل اجباری است',
            'phone.unique' => 'شماره موبایل قبلا انتخاب شده است',
            'password.required' => 'وارد کردن رمز عبور الزامی است',
            'password.confirmed' => 'تکرار رمز عبور با خود رمز عبور مطابقت ندارد',
            'national_code.digits' => 'کد ملی باید ۱۰ رقم باشد',
            'national_code.unique' => 'کد ملی قبلا ثبت شده است',
        ];
    }

    protected function prepareForValidation()
    {
        // Modify the 'phone' value if it's present in the request
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }

        if ($this->filled('national_code') === false) {
            $this->merge(['national_code' => null]);
        }

        if ($this->filled('business_type') === false) {
            $this->merge(['business_type' => null]);
        }

        if ($this->filled('dealing_group_id') === false) {
            $this->merge(['dealing_group_id' => null]);
        }
    }
}
