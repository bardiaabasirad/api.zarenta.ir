<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class PageContactUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required'],
        ];
    }
}
