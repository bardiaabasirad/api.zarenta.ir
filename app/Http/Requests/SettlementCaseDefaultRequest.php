<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettlementCaseDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'replacement_loss' => ['required', 'numeric', 'min:0'],
            'use_partner_trade' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
