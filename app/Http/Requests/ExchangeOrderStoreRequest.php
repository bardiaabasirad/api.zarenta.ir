<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ExchangeOrderStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weight' => ['required','numeric'],
        ];
    }
}
