<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewayRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes','in:active,inactive'],
            'owner_card_payment' => ['sometimes','boolean'],
            'merchant_id' => ['sometimes'],
            'key' => ['sometimes'],
        ];
    }

    public function messages()
    {
        return [
            'status.in' => 'مقدار فیلد وضعیت نادرست است',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('owner_card_payment')) {
            $this->merge([
                'owner_card_payment' => $this->toBoolean($this->input('owner_card_payment')),
            ]);
        }
    }

    /**
     * Convert various string/boolean representations to a real boolean.
     */
    protected function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
