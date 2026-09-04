<?php

namespace App\Services;

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
        $basePrice = $marketPrice['price'] * $variety->weight;

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

        $basePrice = $marketPrice->price * $object->weight;

        if ($object->percentage_buy_wage) $basePrice += $basePrice * ($object->percentage_buy_wage / 100);
        if ($object->tomans_buy_wage) $basePrice += $object->tomans_buy_wage;

        $basePrice *= $count;

        return ceil($basePrice / 1000) * 1000;
    }
}
