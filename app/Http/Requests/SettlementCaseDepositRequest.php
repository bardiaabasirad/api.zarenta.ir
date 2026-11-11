<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettlementCaseDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
