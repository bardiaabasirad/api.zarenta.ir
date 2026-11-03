<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'selected_permissions' => ['sometimes', 'array'],
            'selected_permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'display_name.string' => 'نام سطح دسترسی باید رشته باشد',
            'display_name.max' => 'حداکثر طول نام سطح دسترسی ۲۵۵ کاراکتر می‌باشد',
            'description.string' => 'توضیحات باید رشته باشد',
            'description.max' => 'حداکثر طول توضیحات ۲۵۵ کاراکتر می‌باشد',
            'selected_permissions.array' => 'مجوزها باید از نوع آرایه باشد',
            'selected_permissions.*.exists' => 'یکی از مجوزهای انتخاب‌شده معتبر نیست',
        ];
    }
}
