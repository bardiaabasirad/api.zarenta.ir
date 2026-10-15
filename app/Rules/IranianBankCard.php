<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianBankCard implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 1. بررسی تایپ اولیه و تبدیل به رشته
        if (!is_string($value) && !is_numeric($value)) {
            $fail('validation.card_number')->translate();
            return;
        }

        // 2. حذف فاصله‌ها و خط تیره‌ها (Clean-up)
        $cardNumber = preg_replace('/\D/', '', (string) $value);

        // 3. بررسی طول دقیق ۱۶ رقم برای کارت‌های بانکی
        if (strlen($cardNumber) !== 16) {
            $fail('validation.card_number_length')->translate([
                'attribute' => $attribute,
                'digits'    => 16,
            ]);
            return;
        }

        // 4. اعتبارسنجی توسط الگوریتم Luhn (Mod 10)
        if (!$this->isValidLuhn($cardNumber)) {
            $fail('validation.card_number_checksum')->translate([
                'attribute' => $attribute,
            ]);
        }
    }

    /**
     * بررسی صحت شماره بر اساس الگوریتم Luhn
     *
     * پیاده‌سازی ریاضیاتی:
     * - ارقام را از راست به چپ پیمایش می‌کنیم.
     * - ارقام در جایگاه‌های فرد (از انتها، بجز Check Digit اصلی) در ۲ ضرب می‌شوند.
     * - اگر حاصل‌ضرب > 9 شد، مجموع ارقام آن (یا تفریق عدد 9) در نظر گرفته می‌شود.
     * - در نهایت مجموع کل اعداد باید مضربی از ۱۰ باشد ($sum % 10 === 0).
     */
    private function isValidLuhn(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$i];

            // اگر به رقم‌های ضریب‌دار برخوردیم (موقعیت متناوب)
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9; // ریاضیاتش دقیقا برابر با جمع دو رقم حاصل است: e.g. 14 -> 1+4=5 or 14-9=5
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
