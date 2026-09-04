<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id'    => ['required','exists:products,id'],
            'property_id'   => ['required_without:title','exists:properties,id'],
            'title'         => ['required_without:property_id','max:100','unique:properties,title'],
            'values'        => ['required','array'],
        ];
    }
}
