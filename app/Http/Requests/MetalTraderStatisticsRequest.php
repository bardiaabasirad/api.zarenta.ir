<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetalTraderStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start' => ['nullable', 'string'],
            'end' => ['nullable', 'string'],
            'metal_item_id' => ['nullable', 'integer', 'min:1'],
            'order_type' => ['nullable', Rule::in(['buy', 'sell'])],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'succeed', 'rejected'])],
            'preset' => ['nullable', Rule::in(['this_month', 'last_month', 'quarter', 'year', 'custom'])],
        ];
    }
}
