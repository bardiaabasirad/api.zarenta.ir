<?php

namespace App\Http\Requests\Api\MetalOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetalOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quantity'      => 'required|numeric|min:0.001|max:999.000',
            'order_type'    => 'required|in:sell,buy',
            'product_id'    => 'required|exists:metal_items,id',
            'order_id'      => 'nullable|sometimes|max:64',
            'mazane'        => 'nullable|sometimes|numeric',
        ];
    }

    public function attributes()
    {
        return [
            'quantity'      => 'تعداد یا وزن',
            'order_type'    => 'نوع سفارش',
            'product_id'    => 'محصول',
            'order_id'      => 'شناسه سفارش شما',
            'mazane'        => 'مظنه',
        ];
    }
}
