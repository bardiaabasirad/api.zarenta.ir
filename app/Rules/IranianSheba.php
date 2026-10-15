<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class IranianSheba implements ValidationRule
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
        // 1. بررسی تایپ اولیه ورودی
        if (!is_string($value) && !is_numeric($value)) {
            $fail('validation.sheba_invalid')->translate(['attribute' => $attribute]);
            return;
        }

        // 2. نرمال‌سازی: حذف فاصله‌ها، تبدیل به حروف بزرگ و تبدیل اعداد فارسی/عربی به انگلیسی
        $sheba = $this->normalize((string) $value);

        // 3. در صورت نبود پیشوند IR، اضافه کردن خودکار آن (مشابه لاجیک قبلی تو)
        if (!Str::startsWith($sheba, 'IR')) {
            $sheba = 'IR' . $sheba;
        }

        // 4. بررسی ساختار و Regex (دقیقاً IR سپس ۲۴ رقم عددی)
        if (!preg_match('/^IR[0-9]{24}$/', $sheba)) {
            $fail('validation.sheba_format')->translate(['attribute' => $attribute]);
            return;
        }

        // 5. اعتبارسنجی ریاضیاتی بر اساس الگوریتم ISO 7064 (MOD 97)
        if (!$this->isValidIbanChecksum($sheba)) {
            $fail('validation.sheba_checksum')->translate(['attribute' => $attribute]);
        }
    }

    /**
     * تبدیل ارقام فارسی و عربی به انگلیسی و پاکسازی فضاهای خالی
     */
    private function normalize(string $value): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persianDigits, $englishDigits, $value);
        $value = str_replace($arabicDigits, $englishDigits, $value);

        return Str::upper(str_replace([' ', '-', '_'], '', trim($value)));
    }

    /**
     * محاسبه باقیمانده MOD 97 طبق استاندارد بین‌المللی IBAN
     *
     * ساختار:
     * 1. انتقال ۴ کاراکتر اول به انتهای رشته (Move IRxx to end)
     * 2. جایگزینی کاراکترهای I=18 و R=27
     * 3. محاسبه $number % 97 == 1
     */
    private function isValidIbanChecksum(string $iban): bool
    {
        // انتقال ۴ کاراکتر اول به انتها
        $reordered = substr($iban, 4) . substr($iban, 0, 4);

        // تبدیل IR به 1827 (I -> 18, R -> 27)
        // چون ۲۴ کاراکتر اول رقم هستند، فقط انتهای رشته تبدیل می‌شود
        $numericString = substr($reordered, 0, -4) . '1827' . substr($reordered, -2);

        // اگر اکستنشن bcmath نصب بود، از bcmod استفاده می‌کنیم که در زبان C بهینه شده است
        if (function_exists('bcmod')) {
            return bcmod($numericString, '97') === '1';
        }

        // پیاده‌سازی فال‌بک ریاضیاتی برای جلوگیری از سرریز Integer در صورت نبود bcmath
        return $this->calculateMod97($numericString) === 1;
    }

    /**
     * محاسبه باقیمانده عدد بزرگ بر 97 با تکنیک تکه‌تکه کردن رشته (Chunking)
     */
    private function calculateMod97(string $numericString): int
    {
        $remainder = 0;
        $length = strlen($numericString);

        // پیمایش بلوک‌های ۷ رقمی برای نگه داشتن عملیات زیر سقف مجاز حافظه و دقت ریاضیاتی
        for ($i = 0; $i < $length; $i += 7) {
            $chunk = (string) $remainder . substr($numericString, $i, 7);
            $remainder = ((int) $chunk) % 97;
        }

        return $remainder;
    }
}
