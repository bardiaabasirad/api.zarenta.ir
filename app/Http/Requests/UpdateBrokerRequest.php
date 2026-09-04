<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrokerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'city_id' => ['sometimes','exists:cities,id'],
            'title' => ['sometimes','max:255'],
            'full_name' => ['sometimes','max:255'],
            'address' => ['sometimes'],
            'description' => ['sometimes'],
            'status' => ['sometimes','in:' . implode(',', UserStatus::classConstants())],
            'latitude' => 'sometimes',
            'longitude' => 'sometimes',
        ];
    }

    public function messages()
    {
        return [
            'city_id.exists' => 'شهر نمایندگی نامعتبر است',
            'title.max' => 'نام نمایندگی طولانی است',
            'full_name.max' => 'نام و نام خانوادگی مسئول طولانی است',
            'address.description' => 'توضیحات نمایندگی ضروری است',
            'status.in' => 'وضعیت نمایندگی نامعتبر است',
        ];
    }
}
