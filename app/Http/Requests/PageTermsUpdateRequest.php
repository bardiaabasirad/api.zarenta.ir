<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PageTermsUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required'],
        ];
    }
}
