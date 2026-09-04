<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DealingGroupUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes','required','max:255']
        ];
    }

    public function messages()
    {
        return [
            'name.max' => 'عنوان گروه طولانی است',
        ];
    }
}
