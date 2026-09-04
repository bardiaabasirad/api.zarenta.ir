<?php

namespace App\Services;

use App\Constants\AppConstants;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Variety;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrderService
{
    public static function calculateOrderProfitLoss(Order $order)
    {
        // اگر سفارش نرخ آبشده برایش ثبت شده است
        if ($order->melted) {
            $price = $order->melted / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR;
            $vat = Setting::where('option_key', 'value_added_tax')->first()->option_value;

            $totalProfit = 0;
            $totalPurchase = 0;

            foreach ($order->items as $object) {
                $purchasePrice = self::purchasePrice($object, $price);
                $priceWithoutDiscount = self::priceWithoutDiscount($object, $price, $vat);
                $priceWithDiscount = self::priceWithDiscount($object, $price, $vat);
                $discount = (($priceWithoutDiscount - $priceWithDiscount) / $priceWithoutDiscount) * 100;
                $discount = (float)number_format($discount, 1, '.', '');

                // Get current product data as array
                $productData = $object->product;

                // Add new key-value pair
                $productData['purchasePriceAccordingToMeltedPrice'] = $purchasePrice;
                $productData['priceWithoutDiscountAccordingToMeltedPrice'] = $priceWithoutDiscount;
                $productData['priceWithDiscountAccordingToMeltedPrice'] = $priceWithDiscount;
                $productData['discountAccordingToMeltedPrice'] = $discount;
                $productData['totalProfitAccordingToMeltedPrice'] = round(($priceWithDiscount - $purchasePrice) * $object->count);

                // Update the entire JSON column
                $object->product = $productData;
                $object->save();

                $totalPurchase += $purchasePrice;
                $totalProfit += $priceWithDiscount - $purchasePrice;
            }

            $order->registered_melted_gold_order_total_profit = round($totalProfit * $object->count);
            $order->registered_melted_gold_order_total_purchase = round($totalPurchase * $object->count);
            $order->save();
        }
    }

    private static function purchasePrice($object, $price)
    {
        $basePrice = $price * $object->product['weight'];

        if ($object->product['percentage_buy_wage']) $basePrice += $basePrice * ($object->product['percentage_buy_wage'] / 100);
        if ($object->product['tomans_buy_wage']) $basePrice += $object->product['tomans_buy_wage'];

        return $basePrice;
    }

    private static function priceWithDiscount($object, $price, $vat = 0.09)
    {
        list($basePrice, $sellWage, $profit, $vatValue) = self::calculatePrice($object, $price, $vat);

        $discount = 0;
        if ($object->product['percentage_discount']) {
            $discount += ($basePrice + $sellWage + $profit) * $object->product['percentage_discount'] / 100;
        }
        if ($object->product['tomans_discount']) {
            $discount += $object->product['tomans_discount'];
        }

        return $basePrice + $sellWage + $profit + $vatValue - $discount;
    }

    public static function priceWithoutDiscount($object, $price, $vat = 0.09)
    {
        list($basePrice, $sellWage, $profit, $vatValue) = self::calculatePrice($object, $price, $vat);

        return $basePrice + $sellWage + $profit + $vatValue;
    }

    private static function calculatePrice($object, $price, $vat = 0.09): array
    {
        $basePrice = $price * $object->product['weight'];

        $sellWage = 0;
        if ($object->product['percentage_sell_wage']) $sellWage += $basePrice * ($object->product['percentage_sell_wage'] / 100);
        if ($object->product['tomans_sell_wage']) $sellWage += $object->product['tomans_sell_wage'];

        $profit = 0;
        if ($object->product['percentage_profit']) $profit += ($basePrice + $sellWage) * $object->product['percentage_profit'] / 100;
        if ($object->product['tomans_profit']) $profit += $object->product['tomans_profit'];

        $vatValue = 0;
        if ($object->product['vat'] == 'active') {
            $vatValue += ($sellWage + $profit) * $vat;
        }

        return array($basePrice, $sellWage, $profit, $vatValue);
    }

    /**
     * @param Product $product
     * @param Variety $variety
     * @param float|int $priceWithDiscount
     * @param float|int $priceWithoutDiscount
     * @param float $discount
     * @param float|int $buyPrice
     * @return Collection
     */
    public static function getCollectProduct(Model $product, Variety $variety, float|int $priceWithDiscount, float|int $priceWithoutDiscount, float $discount, float|int $buyPrice): \Illuminate\Support\Collection
    {
        return collect([
            'id' => $product->id,
            'title' => $product->title,
            'carat' => $product->gold_carat,
            'description' => $product->description,
            'size_unit' => $product->size_unit ? $product->size_unit->unit : null,
            'color' => $variety->color ? $variety->color->color_name : null,
            'hex_code' => $variety->color ? $variety->color->hex_code : null,
            'weight' => $variety->weight,
            'size' => $variety->size,
            'gold_price' => $variety->gold_price,
            'vat' => $product->vat,
            'percentage_buy_wage' => $variety->percentage_buy_wage,
            'tomans_buy_wage' => $variety->tomans_buy_wage,
            'percentage_sell_wage' => $variety->percentage_sell_wage,
            'tomans_sell_wage' => $variety->tomans_sell_wage,
            'percentage_profit' => $variety->percentage_profit,
            'tomans_profit' => $variety->tomans_profit,
            'percentage_discount' => $variety->percentage_discount,
            'tomans_discount' => $variety->tomans_discount,
            'image' => $variety->images->isNotEmpty() ? ProductService::getImage($variety) : null,
            'price_with_discount' => $priceWithDiscount,
            'price_without_discount' => $priceWithDiscount != $priceWithoutDiscount ? $priceWithoutDiscount : null,
            'discount' => $discount > 0 ? $discount : null,
            'total_profit' => $priceWithDiscount - $buyPrice,
        ]);
    }
}
