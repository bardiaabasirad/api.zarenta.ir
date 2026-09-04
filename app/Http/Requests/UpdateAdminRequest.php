<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['sometimes','regex:/^(\+989|989|09|9)\d{9}$/','unique:admins'],
            'password' => ['sometimes','string','min:8','max:255','confirmed'],
            'full_name' => ['sometimes','string','max:255'],
            'status' => ['sometimes','in:' . implode(',', UserStatus::classConstants())],
            'roles' => ['sometimes','array'],
            'image' => ['sometimes','file','mimes:jpg,png','max:1024'],
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'فرمت تلفن وارد شده نادرست است',
            'phone.unique' => 'تلفن وارد شده تکراری است',
            'password.min' => 'حداقل طول کلمه عبور ۸ کاراکتر می‌باشد',
            'password.max' => 'حداکثر طول کلمه عبور ۲۵۵ کاراکتر می‌باشد',
            'password.confirmed' => 'تکرار کلمه عبور صحیح نیست',
            'full_name.string' => 'نام و نام خانوادگی باید رشته باشد',
            'full_name.max' => 'حداکثر طول نام و نام خانوادگی ۲۵۵ کاراکتر می‌باشد',
            'status.in' => 'مقدار فیلد وضعیت نادرست است',
            'roles.array' => 'مجوزها باید از نوع آرایه باشد',
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
