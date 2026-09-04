<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'starts_at' => 'sometimes|required|date_format:Y-m-d',
            'ends_at' => 'sometimes|required|date_format:Y-m-d|after_or_equal:starts_at',
            'amount' => 'sometimes|numeric|min:0',
            'features' => 'sometimes|required|array',
            'features.*' => 'exists:subscription_features,id',
        ];
    }

    public function messages()
    {
        return [
            'starts_at.required' => 'تاریخ شروع اشتراک ضروری است',
            'ends_at.required' => 'تاریخ پایان اشتراک ضروری است',
        ];
    }
}
