<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectedAutoOrderExchangeUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'min' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'regex:/^\d+(\.\d{1,3})?$/'
            ],
            'max' => [
                'sometimes',
                'required',
                'numeric',
                'between:0.001,99999',
                'regex:/^\d+(\.\d{1,3})?$/'
            ],
            'status' => ['sometimes','required','in:active,inactive'],
            'type' => ['sometimes','required','in:tomorrow,day_after_tomorrow,coins'],
            'validity_period' => ['sometimes','required', 'integer']
        ];
    }
}
