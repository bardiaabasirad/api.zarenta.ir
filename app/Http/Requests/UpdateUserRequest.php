<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes','required','max:255'],
            'phone' => ['sometimes','required','regex:/^9\d{9}$/','unique:users,phone,' . $this->user->id],
            'national_code' => ['sometimes','required','digits:10'],
            'born_at' => ['sometimes', 'required'],
            'status' => ['sometimes','in:' . implode(',', UserStatus::classConstants())],
            'allowed_bullion_ordering' => ['sometimes','in:active,inactive'],
            'ban_reason' => ['sometimes','nullable'],
            'ban_description' => ['sometimes','nullable'],
            'kimi_account_id' => ['sometimes', 'nullable', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'full_name.max' => 'نام و نام خانوادگی است',
            'phone.regex' => 'شماره موبایل نامعتبر است',
            'phone.unique' => 'شماره موبایل قبلا انتخاب شده است',
            'national_code.digits' => 'کد ملی نامعتبر است',
            'status.in' => 'وضعیت کاربر نامعتبر است',
        ];
    }

    protected function prepareForValidation()
    {
        // Modify the 'phone' value if it's present in the request
        if ($this->has('phone')) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
