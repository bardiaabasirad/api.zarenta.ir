<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartQuantityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'variety_id' => ['bail', 'required', 'integer', 'exists:varieties,id'],
        ];

        /**
         * The 'bail' rule instructs Laravel to stop validating a field as soon as it
         * encounters its first validation failure.
         *
         * Example without 'bail': ['required', 'integer', 'exists:products,id']
         * 1. 'required' fails -> triggers "The product field is required" error.
         * 2. 'integer' fails -> triggers "The product must be an integer" error.
         * 3. 'exists' might still run or be skipped based on the null value.
         *
         * Result: The error bag may contain multiple redundant messages for a single field.
         * Using 'bail' prevents unnecessary database queries (like 'exists') if basic
         * validation (like 'integer') fails.
         */
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'محصول',
            'variety_id' => 'تنوع',
        ];
    }
}
