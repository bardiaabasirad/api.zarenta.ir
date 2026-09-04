<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required','max:255'],
            'phone' => ['required','regex:/^9\d{9}$/','unique:metal_traders,phone'],
            'kimi_account_id' => ['sometimes','nullable'],
            'trade_leverage' => ['sometimes','nullable','integer','min:1','max:255'],
            'dealing_group_id' => ['sometimes','nullable','exists:dealing_groups,id'],
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
            'kimi_account_id.required' => 'شناسه حسابداری کیمیا اجباری است',
        ];
    }

    protected function prepareForValidation()
    {
        // Modify the 'phone' value if it's present in the request
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
