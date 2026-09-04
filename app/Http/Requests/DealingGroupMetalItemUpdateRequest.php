<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DealingGroupMetalItemUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'buy_fee_margin' => ['sometimes','required','integer'],
            'sell_fee_margin' => ['sometimes','required','integer'],
            'min_order' => ['sometimes','required','integer'],
            'max_order' => ['sometimes','required','integer'],
            'tolerance_type' => ['sometimes','in:percentage,fixed_amount'],
        ];
    }

    public function messages()
    {
        return [

        ];
    }
}
