<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DealingGroupStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'max:255'],
            'tolerance_type' => ['required', 'in:fixed_amount,percentage'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'عنوان گروه الزامی است',
            'name.max' => 'عنوان گره طلانی است',
        ];
    }
}
