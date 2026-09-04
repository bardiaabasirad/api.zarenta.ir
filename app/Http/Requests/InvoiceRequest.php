<?php

namespace App\Http\Requests;

use App\Models\CityShippingMethod;
use App\Models\Variety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->market_price)) {
            $decoded = json_decode($this->market_price, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'market_price' => $decoded,
                ]);
            }
        }

        if (is_string($this->address)) {
            $decoded = json_decode($this->address, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'address' => $decoded,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'cart_price' => ['required', 'numeric', 'min:0'],
            'cart_discount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'payable' => ['bail', 'required', 'numeric', 'min:0'],

            'items' => ['bail', 'required', 'array', 'min:1'],
            'items.*' => ['bail', 'required', 'array'],

            'items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'items.*.variety_id' => ['bail', 'required', 'integer', 'exists:varieties,id'],
            'items.*.count' => ['bail', 'required', 'integer', 'min:1'],

            'market_price' => ['bail', 'required', 'array'],
            'market_price.id' => ['bail', 'required', 'integer', 'exists:market_prices,id'],
            'market_price.price' => ['bail', 'required', 'numeric', 'min:0'],

            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'delivery_method' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'driver' => ['nullable', Rule::in(['jibit', 'azki'])],

            'address' => ['nullable', 'array'],
        ];
    }

    /**
     * اعتبارسنجی تکمیلی برای روابط منطقی بین فیلدها
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->any()) return;

                $items = $this->input('items', []);

                // ۱. بررسی تطابق Variety با Product
                foreach ($items as $index => $item) {
                    $exists = Variety::where('id', $item['variety_id'])
                        ->where('product_id', $item['product_id'])
                        ->exists();

                    if (!$exists) {
                        $validator->errors()->add(
                            "items.{$index}.variety_id",
                            "تنوع انتخاب شده متعلق به این محصول نمی‌باشد."
                        );
                    }
                }

                // ۲. بررسی تطابق روش ارسال با شهر
                if ($this->filled(['delivery_method', 'city_id'])) {
                    $isAvailable = CityShippingMethod::where('shipping_method_id', $this->delivery_method)
                        ->where('city_id', $this->city_id)
                        ->where('status', 'active')
                        ->exists();

                    if (!$isAvailable) {
                        $validator->errors()->add(
                            'delivery_method',
                            'روش ارسال انتخاب شده برای شهر شما در دسترس نیست.'
                        );
                    }
                }
            }
        ];
    }

    public function attributes(): array
    {
        return [
            'cart_price' => 'قیمت کالاها',
            'cart_discount' => 'تخفیف',
            'shipping_cost' => 'هزینه ارسال',
            'payable' => 'مبلغ قابل پرداخت',
            'items' => 'آیتم‌های سبد خرید',
            'items.*.product_id' => 'محصول',
            'items.*.variety_id' => 'تنوع',
            'items.*.count' => 'تعداد',
            'market_price' => 'قیمت بازار',
            'market_price.id' => 'شناسه قیمت بازار',
            'market_price.price' => 'مبلغ قیمت بازار',
            'city_id' => 'شهر',
            'delivery_method' => 'روش ارسال',
            'driver' => 'درگاه پرداخت',
            'address' => 'آدرس',
        ];
    }

    public function messages(): array
    {
        return [
            'cart_price.required' => 'قیمت کالاها الزامی است.',
            'cart_price.numeric' => 'قیمت کالاها باید عددی باشد.',
            'cart_discount.numeric' => 'تخفیف باید عددی باشد.',
            'shipping_cost.numeric' => 'هزینه ارسال باید عددی باشد.',
            'payable.required' => 'مبلغ قابل پرداخت الزامی است.',
            'payable.numeric' => 'مبلغ قابل پرداخت باید عددی باشد.',

            'items.required' => 'آیتم‌های سبد خرید الزامی است.',
            'items.array' => 'آیتم‌های سبد خرید نامعتبر است.',
            'items.min' => 'سبد خرید نمی‌تواند خالی باشد.',

            'market_price.required' => 'اطلاعات قیمت بازار الزامی است.',
            'market_price.array' => 'اطلاعات قیمت بازار نامعتبر است.',

            'driver.in' => 'درگاه پرداخت انتخاب شده معتبر نیست.',
        ];
    }
}
