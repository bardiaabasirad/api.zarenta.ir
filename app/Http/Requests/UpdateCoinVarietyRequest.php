<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoinVarietyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'coin_id' => ['sometimes','exists:coins,id'],
            'weight' => ['sometimes','numeric','between:0,999.999'],
            'percentage_profit' => ['sometimes','integer','between:0,99'],
            'tomans_profit' => ['sometimes','integer','between:0,99999999'],
            'percentage_wage' => ['sometimes','integer','between:0,99'],
            'tomans_wage' => ['sometimes','integer','between:0,99999999'],
        ];
    }
}
