<?php

use App\Constants\AppConstants;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Illuminate\Support\Str;

if (!function_exists('getPhone')) {
    function getPhone($phone = ''): string
    {
        // مدیریت ورودی غیررشته‌ای
        if (is_array($phone)) {
            $phone = reset($phone) ?: '';
        }

        $phone = (string) $phone;
        $phone = trim($phone);

        if ($phone) {
            // Remove "98" or "+98" only if they appear at the beginning of the phone number and length of phone is greater than 11 characters.
            if (strlen($phone) > 11) {
                $phone = preg_replace('/^(98|\+98)/', '', $phone);
            }

            // If the remaining string has a length of 11, remove the first character
            if (strlen($phone) == 11) {
                $phone = substr($phone, 1);
            }

            return $phone;
        }

        return '';
    }
}

if (!function_exists('calculateEstimatedReadingTime')) {
    function calculateEstimatedReadingTime(string $html)
    {
        $stripedTags = strip_tags($html);

        preg_match_all('/[\pL\pN\pPd]+/u', $stripedTags, $matches);
        $characterCount = count($matches[0]);

        if ($characterCount <= 200) {
            $readMinute = 1;
        } else {
            $readMinute = ceil($characterCount / 200);
        }
        return $readMinute;
    }
}

if (!function_exists('createPersianSlug')) {
    function createPersianSlug($string)
    {
        // Transliterate Persian characters to Latin characters
        $latinString = persianToLatin($string);

        // Remove any characters that are not letters, numbers, or hyphens
        $slug = preg_replace('/[^a-zA-Z0-9-]+/', '-', $latinString);

        // Remove leading and trailing hyphens
        $slug = trim($slug, '-');

        // Convert to lowercase
        $slug = strtolower($slug);

        return $slug;
    }
}

if (!function_exists('persianToLatin')) {
    function persianToLatin($string)
    {
        $persianChars = ['ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی'];
        $latinChars = ['a', 'b', 'p', 't', 's', 'j', 'ch', 'h', 'kh', 'd', 'z', 'r', 'z', 'zh', 's', 'sh', 's', 'z', 't', 't', 'a', 'gh', 'f', 'q', 'k', 'g', 'l', 'm', 'n', 'o', 'h', 'i'];

        return str_replace($persianChars, $latinChars, $string);
    }
}

if (!function_exists('englishToPersianNumbers')) {
    function englishToPersianNumbers($englishNumber)
    {
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($englishNumbers, $persianNumbers, $englishNumber);
    }
}

if (!function_exists('maskPhoneNumber')) {
    function maskPhoneNumber($phoneNumber)
    {
        $firstPart = substr($phoneNumber, 0, 5);
        $lastPart = substr($phoneNumber, -2);
        $maskedPart = str_repeat('*', 3);

        return $lastPart . $maskedPart . $firstPart;
    }
}

if (!function_exists('isStoreOpenNow')) {
    function isStoreOpenNow()
    {
        $dayOfWeek = strtolower(Carbon::now()->format('l'));
        $workingHour = WorkingHour::where('day_of_week', $dayOfWeek)->first();
        $currentHour = Carbon::now()->format('G');

        if (!$workingHour || !in_array($currentHour, $workingHour['working_hours'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('calculateFormula')) {

    function calculateFormula(string $formula, array $variables = [])
    {
        $formula = trim($formula);

        if ($formula === '') {
            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Allowed functions
        |--------------------------------------------------------------------------
        */
        $allowedFunctions = [
            'ceil',
            'floor',
            'round',
            'abs',
            'sqrt',
            'max',
            'min',
        ];

        /*
        |--------------------------------------------------------------------------
        | Replace variables safely
        |--------------------------------------------------------------------------
        */
        foreach ($variables as $var => $value) {

            $value = $value ?? 0;

            // فقط نام متغیر معتبر
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $var)) {
                throw new RuntimeException("Invalid variable name: {$var}");
            }

            // جایگزینی دقیق متغیر
            $formula = preg_replace(
                '/\b' . preg_quote($var, '/') . '\b/',
                (string)$value,
                $formula
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate functions
        |--------------------------------------------------------------------------
        */
        preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $formula, $matches);

        foreach ($matches[1] as $functionName) {

            if (!in_array($functionName, $allowedFunctions)) {
                throw new RuntimeException("Function not allowed: {$functionName}");
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Final character validation
        |--------------------------------------------------------------------------
        */
        if (!preg_match('/^[0-9+\-*\/().,%\s_a-zA-Z]+$/', $formula)) {
            throw new RuntimeException("Invalid characters in formula.");
        }

        /*
        |--------------------------------------------------------------------------
        | Execute
        |--------------------------------------------------------------------------
        */
        try {

            $result = 0;

            eval('$result = ' . $formula . ';');

            return $result;

        } catch (Throwable $e) {

            throw new RuntimeException(
                'Formula calculation failed: ' . $e->getMessage()
            );
        }
    }
}


if (!function_exists('roundUpToThousand')) {
    function roundUpToThousand($number)
    {
        return (int)(ceil($number / 1000) * 1000);
    }
}

if (!function_exists('roundDownToThousand')) {
    function roundDownToThousand(int $number): int
    {
        return (int)(floor($number / 1000) * 1000);
    }
}

if (!function_exists('hasRealDecimal')) {
    function hasRealDecimal($value)
    {
        // تبدیل ورودی به رشته برای تحلیل دقیق
        $str = (string)$value;

        // اگر نقطه ندارد => اعشاری ندارد
        if (strpos($str, '.') === false) {
            return false;
        }

        // جدا کردن بخش اعشاری
        list($intPart, $decimalPart) = explode('.', $str, 2);

        // اگر کل بخش اعشاری صفر باشد => اعشار واقعی ندارد
        // حذف صفرهای سمت راست برای بررسی اینکه چیزی غیر از صفر وجود دارد
        return intval($decimalPart) !== 0;
    }
}

if (!function_exists('priceStatus')) {
    function priceStatus($dateTime, $priceValidityPeriod)
    {
        $givenDateTime = Carbon::parse($dateTime);
        $currentDateTime = Carbon::now();
        $secondsDifference = $currentDateTime->diffInSeconds($givenDateTime);

        if ($secondsDifference > $priceValidityPeriod) {
            return "close";
        } else {
            return "open";
        }
    }
}

if (!function_exists('getDecimalCount')) {
    function getDecimalCount($number)
    {
        // تبدیل به رشته و حذف صفرهای انتهایی
        $string = (string)$number;

        if (strpos($string, '.') === false) {
            return 0;
        }

        $decimalPart = explode('.', $string)[1];
        $decimalPart = rtrim($decimalPart, '0');

        return strlen($decimalPart);
    }
}

if (!function_exists('jalali')) {
    function jalali($date, $format = 'Y/m/d')
    {
        return \Morilog\Jalali\Jalalian::fromDateTime($date)->format($format);
    }
}

if (!function_exists('buildTransactionDescription')) {
    function buildTransactionDescription($item)
    {
        $text = '';

        // اگر ActionName وجود داشته باشد
        if (!empty($item['ActionName'])) {
            $text .= $item['ActionName'] . ' ';
        }

        // اگر Description وجود داشته باشد
        if (!empty($item['Description'])) {
            // نگاشت عبارت‌های مورد نظر
            $map = [
                'سکه امامی' => 'تمام سکه ۸۶',
                'سکه نیم' => 'نیم سکه ۸۶',
                'سکه ربع' => 'ربع سکه ۸۶',
                'سکه قدیم' => 'تمام سکه قدیم',
            ];

            $desc = $item['Description'];

            // جایگزینی همه موارد
            foreach ($map as $key => $value) {
                $desc = preg_replace('/' . preg_quote($key, '/') . '/u', $value, $desc);
            }

            $text .= $desc . ' ';
        }

        // اگر GoldPrice موجود باشد
        if (isset($item['GoldPrice']) && $item['GoldPrice'] !== '' && $item['GoldPrice'] !== null) {
            $text .= number_format(round($item['GoldPrice'] / 10)) . ' ';
        }

        // اگر UnitPrice موجود باشد و معتبر باشد
        if (!empty($item['UnitPrice']) && $item['UnitPrice'] != 0) {

            if (
                !in_array($item['ActionName'], ['پرداخت', 'دریافت']) ||
                !in_array($item['CurrencyId'], [15, 16, 17, 18])
            ) {
                $unitPriceToman = round($item['UnitPrice'] / 10);
                $text .= 'فی ' . number_format($unitPriceToman) . ' تومان ';
            }

            if (!empty($item['Quantity']) && $item['Quantity'] != 0) {
                $text .= 'تعداد ' . number_format(abs($item['Quantity'])) . ' ';
            }
        }

        // بررسی Fineness
        if (isset($item['Weight750']) && !empty($item['Fineness']) && $item['Fineness'] != 750 && $item['ProductId'] == 2) {
            $text .= abs(round(($item['Weight750'] / $item['Weight']) * 750)) . 'K';
        } elseif (!empty($item['Fineness']) && $item['Fineness'] != 750) {
            $text .= $item['Fineness'] . 'K';
        }

        return trim($text);
    }
}

function buildBalanceSummary(array $balances): string
{
    $orderedGoldIds = [
        AppConstants::KIMIA_TODAY_SPOT_SETTLEMENT,
        AppConstants::KIMIA_TOMORROW_SPOT_SETTLEMENT,
        AppConstants::KIMIA_DAY_AFTER_TOMORROW_SPOT_SETTLEMENT
    ];

    $orderedCoinIds = [
        AppConstants::KIMIA_GOLD_COIN_86,
        AppConstants::KIMIA_GOLD_HALF_COIN_86,
        AppConstants::KIMIA_GOLD_QUARTER_COIN_86,
        AppConstants::KIMIA_GOLD_COIN_OLD_VERSION
    ];

    // فیلتر کردن موجودی‌های طلا
    $goldBalances = array_filter(
        array_map(function ($id) use ($balances) {
            foreach ($balances as $b) {
                if ($b['CurrencyId'] === $id) {
                    return $b;
                }
            }
            return null;
        }, $orderedGoldIds)
    );

    // فیلتر کردن موجودی‌های سکه
    $coinBalances = array_filter(
        array_map(function ($id) use ($balances) {
            foreach ($balances as $b) {
                if ($b['CurrencyId'] === $id) {
                    return $b;
                }
            }
            return null;
        }, $orderedCoinIds)
    );

    // تابع تعیین رنگ
    $getColor = function ($n) {
        return $n > 0 ? 'blue' : ($n < 0 ? 'red' : 'black');
    };

    // تابع تبدیل عدد به فارسی
    $numFa = function ($n, $decimalPoints = 3) {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $formattedNumber = number_format(abs($n), $decimalPoints, '.', ',');
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $persianDigits,
            $formattedNumber
        );
    };

    // تابع برچسب پول
    $moneyLabel = function ($cid) {
        switch ($cid) {
            case AppConstants::KIMIA_TODAY_SPOT_SETTLEMENT:
                return 'تومان';
            case AppConstants::KIMIA_TOMORROW_SPOT_SETTLEMENT:
                return 'فردایی';
            case AppConstants::KIMIA_DAY_AFTER_TOMORROW_SPOT_SETTLEMENT:
                return 'پس‌فردایی';
            default:
                return '';
        }
    };

    // تابع برچسب سکه
    $coinLabel = function ($cid) {
        switch ($cid) {
            case AppConstants::KIMIA_GOLD_COIN_OLD_VERSION:
                return 'تمام سکه قدیم';
            case AppConstants::KIMIA_GOLD_COIN_86:
                return 'تمام سکه ۸۶';
            case AppConstants::KIMIA_GOLD_HALF_COIN_86:
                return 'نیم سکه ۸۶';
            case AppConstants::KIMIA_GOLD_QUARTER_COIN_86:
                return 'ربع سکه ۸۶';
            default:
                return '';
        }
    };

    $html = '
    <div style="margin-top:20px;font-size:12px;direction:rtl">
      <div style="display:flex;font-weight:bold;padding:4px 8px">
        <div style="width:80px">وزن ۷۵۰</div>
        <div>مبلغ</div>
      </div>
    ';

    // اضافه کردن موجودی‌های طلا
    foreach ($goldBalances as $b) {
        $weightColor = $getColor($b['Weight']);
        $weightText = $b['Weight'] === 0
            ? '۰'
            : ($b['Weight'] > 0
                ? 'بس ' . $numFa($b['Weight'])
                : 'بد ' . $numFa($b['Weight']));

        $moneyColor = $getColor($b['Money']);
        $moneyText = $b['Money'] === 0
            ? '۰'
            : ($b['Money'] > 0
                ? 'بس ' . $numFa(round($b['Money'] / 10), 0)
                : 'بد ' . $numFa(round(abs($b['Money'] / 10)), 0));

        $label = $moneyLabel($b['CurrencyId']);

        $html .= "
      <div style=\"display:flex;justify-content:space-between;align-items:center;border:1px solid #ccc;padding:6px 8px;border-radius:6px;margin:4px 0\">
        <div style=\"width:80px;color:{$weightColor}\">
          {$weightText}
        </div>
        <div style=\"display:flex;align-items:center;justify-content:space-between;flex:1;gap:8px;\">
          <div style=\"color:{$moneyColor}\">
            {$moneyText}
          </div>
          <div style=\"background:#dcfce7;color:#166534;border-radius:10px;padding:0 4px;font-size:11px\">{$label}</div>
        </div>
      </div>";
    }

    // اضافه کردن موجودی‌های سکه
    if (count($coinBalances) > 0) {
        foreach ($coinBalances as $b) {
            $moneyColor = $getColor($b['Money']);
            $moneyText = $b['Money'] === 0
                ? '۰'
                : ($b['Money'] > 0
                    ? 'بس ' . $numFa($b['Money'], 0)
                    : 'بد ' . $numFa(abs($b['Money']), 0));

            $label = $coinLabel($b['CurrencyId']);

            $html .= "
        <div style=\"display:flex;justify-content:space-between;align-items:center;border:1px solid #ccc;padding:6px 8px;border-radius:6px;margin:4px 0\">
          <div style=\"color:{$moneyColor}\">
            {$moneyText}
          </div>
          <div style=\"background:#dcfce7;color:#166534;border-radius:10px;padding:0 4px;font-size:11px\">{$label}</div>
        </div>";
        }
    }

    $html .= '</div>';
    return $html;
}
