<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Morilog\Jalali\CalendarUtils;

class NcrRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'national_code' => ['required'],
            'birthdate' => ['required'],
            'firstname' => ['required_without_all:lastname,father_name'],
            'lastname' => ['required_without_all:firstname,father_name'],
            'father_name' => ['required_without_all:firstname,lastname'],
        ];
    }

    public function messages()
    {
        return [
            'national_code.required' => 'کد ملی ضروری است',
            'birthdate.required' => 'تاریخ تولد ضروری است',
            'firstname.required_without_all' => 'حداقل یکی از پارامترهای نام، نام خانوادگی یا نام پدر ضروری است',
            'lastname.required_without_all' => 'حداقل یکی از پارامترهای نام، نام خانوادگی یا نام پدر ضروری است',
            'father_name.required_without_all' => 'حداقل یکی از پارامترهای نام، نام خانوادگی یا نام پدر ضروری است',
        ];
    }
}
