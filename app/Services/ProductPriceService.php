<?php

namespace App\Services;

use App\Constants\AppConstants;

class ProductPriceService
{
    public static function priceWithDiscount($variety, $marketPrice, $vatStatus, $vat = 0.09): float|int
    {
        if (!$marketPrice || !$variety) return 0;

        list($basePrice, $sellWage, $profit, $vatValue) = self::calculatePrice($marketPrice, $variety, $vatStatus, $vat);

        $discount = 0;
        if ($variety->percentage_discount) {
            $discount += ($basePrice + $sellWage + $profit) * $variety->percentage_discount / 100;
        }
        if ($variety->tomans_discount) {
            $discount += $variety->tomans_discount;
        }

        return ceil(($basePrice + $sellWage + $profit + $vatValue - $discount) / 1000) * 1000;
    }

    public static function priceWithoutDiscount($variety, $marketPrice, $vatStatus, $vat = 0.09)
    {
        if (!$marketPrice || !$variety) return 0;

        list($basePrice, $sellWage, $profit, $vatValue) = self::calculatePrice($marketPrice, $variety, $vatStatus, $vat);

        return ceil(($basePrice + $sellWage + $profit + $vatValue) / 1000) * 1000;
    }

    private static function calculatePrice($marketPrice, $variety, $vatStatus, mixed $vat): array
    {
        $basePrice = self::baseGoldValue($marketPrice, $variety->weight, self::resolveGoldCarat($variety));

        $sellWage = 0;
        if ($variety->percentage_sell_wage) $sellWage += $basePrice * ($variety->percentage_sell_wage / 100);
        if ($variety->tomans_sell_wage) $sellWage += $variety->tomans_sell_wage;

        $profit = 0;
        if ($variety->percentage_profit) $profit += ($basePrice + $sellWage) * $variety->percentage_profit / 100;
        if ($variety->tomans_profit) $profit += $variety->tomans_profit;

        $vatValue = 0;
        if ($vatStatus == 'active') {
            $vatValue += ($sellWage + $profit) * $vat;
        }
        return array($basePrice, $sellWage, $profit, $vatValue);
    }

    public static function purchasePrice($object, $marketPrice, $count = 1)
    {
        if (!$object) return 0;

        $basePrice = self::baseGoldValue($marketPrice, $object->weight, self::resolveGoldCarat($object));

        if ($object->percentage_buy_wage) $basePrice += $basePrice * ($object->percentage_buy_wage / 100);
        if ($object->tomans_buy_wage) $basePrice += $object->tomans_buy_wage;

        $basePrice *= $count;

        return ceil($basePrice / 1000) * 1000;
    }

    /**
     * ارزش طلای خام بر اساس نرخ ۱۸ عیار و وزن معادل عیار محصول.
     */
    public static function baseGoldValue($marketPrice, $weight, $goldCarat = null): float
    {
        $price = is_array($marketPrice)
            ? (float) $marketPrice['price']
            : (float) $marketPrice->price;

        return $price * self::equivalentWeight18k($weight, $goldCarat);
    }

    /**
     * تبدیل وزن فیزیکی به وزن معادل طلای ۱۸ عیار (۷۵۰).
     */
    public static function equivalentWeight18k($weight, $goldCarat = null): float
    {
        return (float) $weight * self::caratFactor($goldCarat);
    }

    public static function caratFactor($goldCarat = null): float
    {
        $carat = (float) ($goldCarat ?: AppConstants::BASE_GOLD_CARAT);

        if ($carat <= 0) {
            $carat = AppConstants::BASE_GOLD_CARAT;
        }

        return $carat / AppConstants::BASE_GOLD_CARAT;
    }

    /**
     * استخراج عیار از variety، product، یا snapshot سفارش.
     */
    public static function resolveGoldCarat($object): float
    {
        if (!$object) {
            return AppConstants::BASE_GOLD_CARAT;
        }

        foreach (['gold_carat', 'carat'] as $key) {
            $value = is_array($object) ? ($object[$key] ?? null) : ($object->{$key} ?? null);
            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        $product = is_array($object)
            ? ($object['product'] ?? null)
            : ($object->product ?? null);

        if ($product) {
            foreach (['gold_carat', 'carat'] as $key) {
                $value = is_array($product) ? ($product[$key] ?? null) : ($product->{$key} ?? null);
                if ($value !== null && $value !== '') {
                    return (float) $value;
                }
            }
        }

        // Variety بدون relation لودشده: از دیتابیس بخوان
        if (is_object($object) && method_exists($object, 'getAttribute') && $object->getAttribute('product_id')) {
            $carat = \App\Models\Product::query()
                ->where('id', $object->getAttribute('product_id'))
                ->value('gold_carat');

            if ($carat !== null && $carat !== '') {
                return (float) $carat;
            }
        }

        return AppConstants::BASE_GOLD_CARAT;
    }

    /**
     * عبارت SQL برای ارزش طلای خام با تبدیل عیار به معادل ۷۵۰.
     */
    public static function sqlGoldValue(string $priceExpr, string $weightExpr = 'varieties.weight', string $caratExpr = 'products.gold_carat'): string
    {
        $base = AppConstants::BASE_GOLD_CARAT;

        return "({$priceExpr} * {$weightExpr} * (COALESCE({$caratExpr}, {$base}) / {$base}))";
    }

    /**
     * عبارت SQL عیار وقتی جدول products در کوئری join نشده باشد.
     */
    public static function sqlCaratSubquery(string $productIdExpr = 'varieties.product_id'): string
    {
        return "(SELECT gold_carat FROM products WHERE products.id = {$productIdExpr})";
    }
}
