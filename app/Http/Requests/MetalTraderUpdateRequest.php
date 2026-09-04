<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetalTraderUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes','required','max:255'],
            'dealing_group_id' => ['sometimes', 'nullable','exists:dealing_groups,id'],
            'kimi_account_id' => ['sometimes','max:255'],
            'trade_leverage' => ['sometimes','nullable','integer','min:1','max:255'],
            'phone' => ['sometimes','required','regex:/^9\d{9}$/','unique:metal_traders,phone,' . $this->metal_trader->id],
            'api_key' => ['sometimes','required','max:32'],
            'min_order' => ['sometimes','required'],
            'max_order' => ['sometimes','required'],
            'balance' => ['sometimes','required'],
            'buy_fee_margin' => ['sometimes','required'],
            'sell_fee_margin' => ['sometimes','required'],
            'block_reason_id' => ['sometimes','exists:block_reasons,id'],
            'description' => ['sometimes','nullable'],
            'status' => ['sometimes','in:active,inactive,rejected'],
            'webhook_url' => ['sometimes', 'nullable', 'url:https', 'max:2048'],
            'webhook_enabled' => ['sometimes', 'boolean:'],
            'aggregated_view_of_invoices' => ['sometimes','in:active,inactive'],
            'market_opening_notification' => ['sometimes','in:active,inactive'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام و نام خانوادگی',
            'dealing_group_id' => 'گروه معاملاتی',
            'kimi_account_id' => 'شناسه حساب کیمیا',
            'trade_leverage' => 'حداکثر سفارش بر حسب ته حساب',
            'phone' => 'شماره موبایل',
            'api_key' => 'کلید ای‌پی‌آی',
            'min_order' => 'حداقل سفارش',
            'max_order' => 'حداکثر سفارش',
            'balance' => 'موجودی',
            'buy_fee_margin' => 'حاشیه کارمزد خرید',
            'sell_fee_margin' => 'حاشیه کارمزد فروش',
            'block_reason_id' => 'دلیل مسدودسازی',
            'description' => 'توضیحات',
            'status' => 'وضعیت',
            'webhook_url' => 'آدرس وب‌هوک',
            'webhook_enabled' => 'وضعیت ارسال نرخ',
            'aggregated_view_of_invoices' => 'نمایش تجمیعی صورت‌حساب‌ها',
            'market_opening_notification' => 'اعلان باز شدن بازار',
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
