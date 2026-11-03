<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdminRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'national_code' => ['required', 'numeric', 'digits:10', 'unique:admins,national_code'],
            'phone' => ['required', 'regex:/^(\+989|989|09|9)\d{9}$/', 'unique:admins,phone'],
            'password' => 'required|string|min:8|max:255|confirmed',
            'status' => ['required', 'in:' . implode(',', UserStatus::classConstants())],
            'roles' => 'sometimes|array',
            'roles.*' => 'integer|exists:roles,id',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages()
    {
        return [
            'national_code.required' => 'کد ملی ضروری است',
            'national_code.numeric' => 'کد ملی وارد شده معتبر نیست',
            'national_code.digits' => 'کد ملی وارد شده معتبر نیست',
            'national_code.unique' => 'کد ملی وارد شده تکراری است',
            'phone.required' => 'فیلد تلفن ضروری است',
            'phone.regex' => 'فرمت تلفن وارد شده نادرست است',
            'phone.unique' => 'تلفن وارد شده تکراری است',
            'password.required' => 'فیلد کلمه عبور ضروری است',
            'password.min' => 'حداقل طول کلمه عبور ۸ کاراکتر می‌باشد',
            'password.max' => 'حداکثر طول کلمه عبور ۲۵۵ کاراکتر می‌باشد',
            'password.confirmed' => 'تکرار کلمه عبور صحیح نیست',
            'full_name.required' => 'فیلد نام و نام خانوادگی ضروری است',
            'full_name.string' => 'نام و نام خانوادگی باید رشته باشد',
            'full_name.max' => 'حداکثر طول نام و نام خانوادگی ۲۵۵ کاراکتر می‌باشد',
            'status.required' => 'فیلد وضعیت ضروری است',
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
