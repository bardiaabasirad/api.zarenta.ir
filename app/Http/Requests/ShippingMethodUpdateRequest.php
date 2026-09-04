<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class ShippingMethodUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'         => ['required','max:100','unique:shipping_methods,title,' . $this->shippingMethod->id],
            'tracking_url'  => ['sometimes','nullable'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'فیلد عنوان ضروری است',
            'title.unique' => 'عنوان قبلا انتخاب شده است',
            'title.max' => 'عنوان نباید بیشتر از :max کاراکتر باشد',
        ];
    }
}
