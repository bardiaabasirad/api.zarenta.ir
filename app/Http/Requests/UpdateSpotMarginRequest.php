<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSpotMarginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'today_spot_settlement_buy_margin' => ['sometimes'],
            'today_spot_settlement_sell_margin' => ['sometimes'],
        ];
    }
}
