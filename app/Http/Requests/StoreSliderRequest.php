<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:150'],
            'key'         => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:sliders,key'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // پاکسازی و تبدیل کلید به فرمت استاندارد snake_case
            'key'       => $this->key ? Str::snake(trim($this->key)) : null,
            'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'وارد کردن نام اسلایدر الزامی است.',
            'key.required'  => 'کلید یکتا الزامی است.',
            'key.unique'    => 'این کلید قبلاً ثبت شده است. لطفاً کلید دیگری انتخاب کنید.',
            'key.regex'     => 'کلید باید فقط شامل حروف کوچک انگلیسی، اعداد و آندرلاین (_) باشد.',
        ];
    }
}
