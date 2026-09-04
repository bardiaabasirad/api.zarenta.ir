<?php

namespace App\Http\Requests;

use App\Rules\NationalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\CalendarUtils;
use Symfony\Component\HttpFoundation\ParameterBag;

class SignupRequest extends FormRequest
{
    public function rules(): array
    {
        $minDate = now()->subYears(120)->format('Y-m-d');

        return [
            'phone' => ['required', 'string', 'regex:/^9\d{9}$/'],

            'full_name' => ['required', 'max:255', 'min:2'],

            'national_code' => ['required', 'string', 'digits:10', new NationalCode],

            'born_at' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $minDate],

            'items' => [
                'sometimes',
                'array',
            ],

            'items.*.product_id' => [
                'required_with:items',
                'integer',
                'min:1',
                'exists:products,id',
            ],

            'items.*.variety_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:varieties,id',
            ],

            'items.*.count' => [
                'required_with:items',
                'integer',
                'min:1',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'شماره تلفن',
            'full_name' => 'نام و نام خانوادگی',
            'national_code' => 'کد ملی',
            'born_at' => 'تاریخ تولد',
            'items' => 'اقلام سبد خرید',
            'items.*.product_id' => 'شناسه محصول',
            'items.*.variety_id' => 'شناسه تنوع محصول',
            'items.*.count' => 'تعداد کالا',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => getPhone($this->input('phone'))]);
        }
    }
}
