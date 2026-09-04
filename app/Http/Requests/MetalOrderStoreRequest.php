<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalOrderStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'metal_item_id'             => ['required', 'exists:metal_items,id'],
            'selected_metal_price_id'   => ['required', 'exists:selected_metal_prices,id'],
            'quantity'                  => ['sometimes', 'numeric'],
            'amount'                    => ['required', 'integer', 'min:0'],
            'frozen'                    => ['required', 'in:cash,metal'],
            'dealing_group_id'          => ['sometimes', 'exists:dealing_groups,id'],
        ];
    }
}
