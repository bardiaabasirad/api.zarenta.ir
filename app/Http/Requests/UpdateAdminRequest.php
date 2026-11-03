<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRequest extends FormRequest
{
    public function rules(): array
    {
        $staffId = $this->route('staff')?->id;

        return [
            'phone' => [
                'sometimes',
                'regex:/^(\+989|989|09|9)\d{9}$/',
                'unique:admins,phone,' . $staffId,
            ],
            'national_code' => [
                'sometimes',
                'numeric',
                'digits:10',
                'unique:admins,national_code,' . $staffId,
            ],
            'password' => ['sometimes', 'string', 'min:8', 'max:255', 'confirmed'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:' . implode(',', UserStatus::classConstants())],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'image' => ['sometimes', 'file', 'mimes:jpg,png', 'max:1024'],
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'فرمت تلفن وارد شده نادرست است',
            'phone.unique' => 'تلفن وارد شده تکراری است',
            'national_code.numeric' => 'کد ملی وارد شده معتبر نیست',
            'national_code.digits' => 'کد ملی وارد شده معتبر نیست',
            'national_code.unique' => 'کد ملی وارد شده تکراری است',
            'password.min' => 'حداقل طول کلمه عبور ۸ کاراکتر می‌باشد',
            'password.max' => 'حداکثر طول کلمه عبور ۲۵۵ کاراکتر می‌باشد',
            'password.confirmed' => 'تکرار کلمه عبور صحیح نیست',
            'full_name.string' => 'نام و نام خانوادگی باید رشته باشد',
            'full_name.max' => 'حداکثر طول نام و نام خانوادگی ۲۵۵ کاراکتر می‌باشد',
            'status.in' => 'مقدار فیلد وضعیت نادرست است',
            'roles.array' => 'نقش‌ها باید از نوع آرایه باشد',
            'roles.*.exists' => 'یکی از نقش‌های انتخاب‌شده معتبر نیست',
            'permissions.array' => 'مجوزها باید از نوع آرایه باشد',
            'permissions.*.exists' => 'یکی از مجوزهای انتخاب‌شده معتبر نیست',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
