<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NationalCode implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) !== 10 || preg_match('/(\d)(\1){9}/', $value)) {
            $fail('کد ملی وارد شده معتبر نیست');
        }
        else {
            $sum = 0;
            $chars = str_split($value);

            for ($i = 0; $i < 9; $i++) {
                $sum += (int)$chars[$i] * (10 - $i);
            }

            $remainder = $sum % 11;
            $lastDigit = $remainder < 2 ? $remainder : 11 - $remainder;

            if ((int)$chars[9] !== $lastDigit){
                $fail('کد ملی وارد شده معتبر نیست');
            }
        }
    }
}
