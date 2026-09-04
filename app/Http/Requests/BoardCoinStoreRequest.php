<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoardCoinStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'metal_item_id'  => ['required', 'integer', 'exists:metal_items,id'],
            'buy_tolerance'  => ['nullable', 'numeric'],
            'sell_tolerance' => ['nullable', 'numeric'],
            'is_featured'    => ['boolean'],
        ];
    }
}
