<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutoOrderSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('auto_order_setting')->id;

        return [
            'metal_item_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('metal_items', 'id'),
                Rule::unique('metal_item_auto_order_settings', 'metal_item_id')->ignore($id),
            ],
            'price_source_id' => ['sometimes', 'required', 'integer', Rule::exists('price_sources', 'id')],
            'is_enabled' => ['sometimes', 'required', 'boolean'],
            'min_auto_buy_weight' => ['nullable', 'numeric', 'min:0'],
            'max_auto_buy_weight' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
