<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutoOrderSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'metal_item_id' => [
                'required', 'integer',
                Rule::exists('metal_items', 'id'),
                Rule::unique('metal_item_auto_order_settings', 'metal_item_id'),
            ],
            'price_source_id' => ['required', 'integer', Rule::exists('price_sources', 'id')],
            'is_enabled' => ['required', 'boolean'],
            'min_auto_buy_weight' => ['nullable', 'numeric', 'min:0'],
            'max_auto_buy_weight' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
