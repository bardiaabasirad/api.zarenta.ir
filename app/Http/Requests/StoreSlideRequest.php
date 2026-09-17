<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // یا بررسی دسترسی کاربر ادمین: auth()->check()
    }

    public function rules(): array
    {
        return [
            'slider_id'    => ['required', 'integer', 'exists:sliders,id'],
            'image'        => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:4096'], // حداکثر 4 مگابایت
            'title'        => ['nullable', 'string', 'max:255'],
            'subtitle'     => ['nullable', 'string', 'max:1000'],
            'action_type'  => ['required', Rule::in(['link', 'product', 'category', 'none'])],
            'action_value' => [
                Rule::excludeIf($this->action_type === 'none'),
                'nullable',
                'string',
                'max:500'
            ],
            'action_text'  => ['nullable', 'string', 'max:100'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    /**
     * پیش‌پردازش داده‌ها قبل از اعتبارسنجی (Data Normalization)
     * برای تبدیل "true"/"false" رشته‌ای به بولین واقعی در FormData
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'  => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            'sort_order' => $this->sort_order ?? 0,
        ]);
    }
}
