<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required','regex:/^9\d{9}$/'],
            'password' => ['required','min:8'],
            'device_name' => ['nullable','max:255'],
        ];
    }

    public function messages()
    {
        return [
            'phone.required' => 'شماره تلفن الزامی است.',
            'phone.regex' => 'شماره تلفن نامعتبر است.',
            'password.required' => 'کلمه عبور الزامی است.',
            'password.min' => 'کلمه عبور حداقل ۸ نویسه است.',
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
