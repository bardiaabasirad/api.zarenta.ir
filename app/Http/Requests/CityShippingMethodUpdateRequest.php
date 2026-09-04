<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class CityShippingMethodUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'shipping_cost'  => ['sometimes','integer','between:0,9999999'],
            'status'  => ['sometimes'],
        ];
    }
}
