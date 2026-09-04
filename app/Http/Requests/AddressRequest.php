<?php

namespace App\Http\Requests;

use App\Rules\NationalCode;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'city_id'        => ['required', 'integer', 'exists:cities,id'],
            'title'          => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone'          => ['required', 'string', 'regex:/^(\+989|989|09|9)\d{9}$/'],
            'national_code'  => ['required', 'string', 'digits:10', new NationalCode],
            'postal_code'    => ['required', 'string', 'digits:10'],
            'address'        => ['required', 'string', 'max:16000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'city_id'        => 'شهر انتخابی',
            'title'          => 'عنوان آدرس',
            'recipient_name' => 'نام دریافت کننده',
            'phone'          => 'شماره تلفن',
            'national_code'  => 'کد ملی',
            'postal_code'    => 'کد پستی',
            'address'        => 'آدرس',
        ];
    }
}
