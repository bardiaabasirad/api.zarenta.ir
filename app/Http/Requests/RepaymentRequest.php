<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepaymentRequest extends FormRequest
{
    /**
     * مجوز دسترسی در خود متد کنترلر با Gate::authorize بررسی می‌شود،
     * بنابراین اینجا true برمی‌گردانیم.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * نرمال‌سازی داده‌ها پیش از اعتبارسنجی.
     * فیلد market به صورت رشته JSON ارسال می‌شود؛ آن را دیکد می‌کنیم
     * تا بتوان روی کلیدهای داخلی‌اش قانون گذاشت.
     */
    protected function prepareForValidation(): void
    {
        $decoded = json_decode($this->input('market'), true);

        if (is_array($decoded)) {
            $this->merge([
                'market' => $decoded,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'market'       => ['required', 'array'],
            'market.id'    => ['required', 'integer', 'exists:market_prices,id'],
            'market.price' => ['required', 'integer', 'min:1'],

            'payable'      => ['required', 'integer', 'min:1'],

            'driver'       => ['sometimes', 'string', Rule::in(['jibit', 'azki'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'market.required'       => 'اطلاعات بازار ارسال نشده است.',
            'market.array'          => 'فرمت اطلاعات بازار نامعتبر است.',
            'market.id.required'    => 'شناسه قیمت بازار الزامی است.',
            'market.id.integer'     => 'شناسه قیمت بازار باید عدد صحیح باشد.',
            'market.id.exists'      => 'قیمت بازار انتخاب‌شده یافت نشد.',
            'market.price.required' => 'قیمت بازار الزامی است.',
            'market.price.integer'  => 'قیمت بازار باید عدد صحیح باشد.',
            'market.price.min'      => 'قیمت بازار نامعتبر است.',

            'payable.required'      => 'مبلغ قابل پرداخت الزامی است.',
            'payable.integer'       => 'مبلغ قابل پرداخت باید عدد صحیح باشد.',
            'payable.min'           => 'مبلغ قابل پرداخت نامعتبر است.',

            'driver.in'             => 'درگاه پرداخت انتخاب‌شده پشتیبانی نمی‌شود.',
        ];
    }
}
