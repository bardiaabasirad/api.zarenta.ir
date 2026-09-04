<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalOrderUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes','in:pending,placed,succeed,failed,rejected,expired'],
            'description' => ['string'],
            'extra_data' => ['string'],
        ];
    }

    public function messages()
    {
        return [];
    }
}
