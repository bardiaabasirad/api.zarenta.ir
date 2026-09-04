<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Morilog\Jalali\CalendarUtils;

class CardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'card' => ['required'],
        ];
    }

    public function messages()
    {
        return [
            'card.required' => 'شماره کارت ضروری است',
        ];
    }
}
