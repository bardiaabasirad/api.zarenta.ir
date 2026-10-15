<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

class IranianBankCardOrSheba implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * مقدار باید یا شماره شبا معتبر باشد یا شماره کارت معتبر.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $ibanValidator = Validator::make(
            [$attribute => $value],
            [
                $attribute => [
                    new IranianSheba(),
                ],
            ]
        );

        // اگر شماره شبا معتبر بود، اعتبارسنجی با موفقیت تمام می‌شود.
        if (!$ibanValidator->fails()) {
            return;
        }

        $cardValidator = Validator::make(
            [$attribute => $value],
            [
                $attribute => [
                    new IranianBankCard(),
                ],
            ]
        );

        // اگر شماره کارت معتبر بود، اعتبارسنجی با موفقیت تمام می‌شود.
        if (!$cardValidator->fails()) {
            return;
        }

        // مقدار نه شبا معتبر است و نه شماره کارت معتبر.
        $fail('validation.iban_or_card')->translate();
    }
}
