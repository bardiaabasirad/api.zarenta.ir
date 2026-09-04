<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'ends_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'amount' => 'sometimes|numeric|min:0',
            'features' => 'required|array',
            'features.*' => 'exists:subscription_features,id',
        ];
    }

    public function messages()
    {
        return [
            'start_date.required' => 'تاریخ شروع اشتراک ضروری است',
            'ends_date.required' => 'تاریخ پایان اشتراک ضروری است',
        ];
    }
}
