<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProductSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'today_spot_settlement_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'today_spot_settlement_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'today_spot_settlement_min_order' => ['sometimes','decimal:0,3'],
            'today_spot_settlement_max_order' => ['sometimes','decimal:0,3'],
            'today_spot_settlement_status' => ['sometimes','in:active,inactive'],

            'tomorrow_spot_settlement_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'tomorrow_spot_settlement_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'tomorrow_spot_settlement_min_order' => ['sometimes','decimal:0,3'],
            'tomorrow_spot_settlement_max_order' => ['sometimes','decimal:0,3'],
            'tomorrow_spot_settlement_status' => ['sometimes','in:active,inactive'],

            'day_after_tomorrow_spot_settlement_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'day_after_tomorrow_spot_settlement_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'day_after_tomorrow_spot_settlement_min_order' => ['sometimes','decimal:0,3'],
            'day_after_tomorrow_spot_settlement_max_order' => ['sometimes','decimal:0,3'],
            'day_after_tomorrow_spot_settlement_status' => ['sometimes','in:active,inactive'],

            'gold_coin_86_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_coin_86_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_coin_86_min_order' => ['sometimes','decimal:0,3'],
            'gold_coin_86_max_order' => ['sometimes','decimal:0,3'],
            'gold_coin_86_status' => ['sometimes','in:active,inactive'],

            'gold_half_coin_86_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_half_coin_86_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_half_coin_86_min_order' => ['sometimes','decimal:0,3'],
            'gold_half_coin_86_max_order' => ['sometimes','decimal:0,3'],
            'gold_half_coin_86_status' => ['sometimes','in:active,inactive'],

            'gold_quarter_coin_86_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_quarter_coin_86_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_quarter_coin_86_min_order' => ['sometimes','decimal:0,3'],
            'gold_quarter_coin_86_max_order' => ['sometimes','decimal:0,3'],
            'gold_quarter_coin_86_status' => ['sometimes','in:active,inactive'],

            'gold_coin_old_version_buy_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_coin_old_version_sell_fee_margin' => ['sometimes','regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'gold_coin_old_version_min_order' => ['sometimes','decimal:0,3'],
            'gold_coin_old_version_max_order' => ['sometimes','decimal:0,3'],
            'gold_coin_old_version_status' => ['sometimes','in:active,inactive'],
        ];
    }
}
