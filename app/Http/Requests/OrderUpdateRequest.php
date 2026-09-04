<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

class OrderUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status'                    => ['required','sometimes','in:' . implode(',', OrderStatus::classConstants())],
            'cancellation_reason'       => ['sometimes','nullable','max:255'],
            'cancellation_details'      => ['sometimes','nullable'],
            'selected_shipping_method'  => ['sometimes','nullable'],
            'tracking_code'             => ['sometimes','nullable'],
            'delivery_details'          => ['sometimes','nullable'],
        ];
    }

    public function messages()
    {
        return [
            'cancellation_reason.max' => 'علت انتخاب شده نامعتبر است',
        ];
    }
}
