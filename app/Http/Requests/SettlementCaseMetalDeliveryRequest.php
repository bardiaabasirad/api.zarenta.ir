<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettlementCaseMetalDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
