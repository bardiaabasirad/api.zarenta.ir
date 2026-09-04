<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class PropertyValueStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'property_id' => ['required','exists:properties,id'],
            'value'=> ['required'],
        ];
    }
}
