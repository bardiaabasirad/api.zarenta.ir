<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes','required','max:255'],
            'dealing_group_id' => ['sometimes', 'nullable','exists:dealing_groups,id'],
            'asset_risk_threshold' => ['sometimes','nullable','numeric','min:0','max:100'],
            'phone' => ['sometimes','required','regex:/^9\d{9}$/','unique:users,phone,' . $this->metal_trader->user_id],
            'national_code' => ['sometimes', 'nullable', 'string', 'digits:10', 'unique:metal_traders,national_code,' . $this->metal_trader->id],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'block_reason_id' => ['sometimes','exists:block_reasons,id'],
            'description' => ['sometimes','nullable'],
            'status' => ['sometimes','in:active,inactive,rejected'],
            'market_opening_notification' => ['sometimes','in:active,inactive'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام و نام خانوادگی',
            'dealing_group_id' => 'گروه معاملاتی',
            'asset_risk_threshold' => 'آستانه ریسک نسبت دارایی',
            'phone' => 'شماره موبایل',
            'national_code' => 'کد ملی',
            'address' => 'آدرس',
            'block_reason_id' => 'دلیل مسدودسازی',
            'description' => 'توضیحات',
            'status' => 'وضعیت',
            'market_opening_notification' => 'اعلان باز شدن بازار',
        ];
    }

    protected function prepareForValidation()
    {
        // Modify the 'phone' value if it's present in the request
        if ($this->has('phone')) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
