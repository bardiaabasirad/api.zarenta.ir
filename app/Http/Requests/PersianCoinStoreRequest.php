<?php

namespace App\Http\Requests;

use App\Enums\PersianCoinStatus;
use Illuminate\Foundation\Http\FormRequest;

class PersianCoinStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weight' => ['required','numeric','gte:0'],
            'percentage_profit' => ['nullable','numeric','between:0,99.99'],
            'tomans_profit' => ['nullable','numeric','between:0,999999999'],
            'percentage_wage' => ['nullable','numeric','between:0,99.99'],
            'tomans_wage' => ['nullable','numeric','between:0,999999999'],
            'status' => ['required','in:' . implode(',', PersianCoinStatus::classConstants())],
        ];
    }
}
