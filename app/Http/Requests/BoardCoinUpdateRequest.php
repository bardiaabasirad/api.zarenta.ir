<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoardCoinUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'metal_item_id'  => ['sometimes', 'required', 'integer', 'exists:metal_items,id'],
            'buy_tolerance'  => ['sometimes', 'nullable', 'numeric'],
            'sell_tolerance' => ['sometimes', 'nullable', 'numeric'],
            'is_featured'    => ['sometimes', 'boolean'],
        ];
    }
}
