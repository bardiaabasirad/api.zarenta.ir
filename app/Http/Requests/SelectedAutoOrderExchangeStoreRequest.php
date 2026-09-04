<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectedAutoOrderExchangeStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reference_channel_id' => ['required', 'integer', 'exists:reference_channels,id'],
            'min' => [
                'required',
                'numeric',
                'min:0',
                'regex:/^\d+(\.\d{1,3})?$/'
            ],
            'max' => [
                'required',
                'numeric',
                'between:0.001,99999',
                'regex:/^\d+(\.\d{1,3})?$/'
            ],
            'validity_period' => ['required','integer'],
            'type' => ['required','in:tomorrow,day_after_tomorrow,coins']
        ];
    }
}
