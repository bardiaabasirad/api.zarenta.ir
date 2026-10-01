<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualFiatOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['deposit', 'withdraw'])],
            'metal_trader_id' => ['required', 'integer', 'exists:metal_traders,id'],
            'value' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'نوع تراکنش (واریز/برداشت)',
            'metal_trader_id' => 'کاربر',
            'value' => 'مبلغ',
            'reference_number' => 'شماره پیگیری',
            'description' => 'توضیحات',
        ];
    }
}
