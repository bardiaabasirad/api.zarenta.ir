<?php

namespace App\Http\Requests;

use App\Enums\PersianCoinStatus;
use Illuminate\Foundation\Http\FormRequest;

class PersianCoinUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weight' => ['sometimes','numeric','gte:0'],
            'percentage_profit' => ['sometimes','nullable','numeric','between:0,99.99'],
            'tomans_profit' => ['sometimes','nullable','numeric','between:0,999999999'],
            'percentage_wage' => ['sometimes','nullable','numeric','between:0,99.99'],
            'tomans_wage' => ['sometimes','nullable','numeric','between:0,999999999'],
            'status' => ['sometimes','in:' . implode(',', PersianCoinStatus::classConstants())],
        ];
    }
}
