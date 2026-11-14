<?php

namespace App\Services;

class ProductService
{
    public static function priceWithDiscount($variety, $marketPrice, $vatStatus, $vat = 0.09): float|int
    {
        return ProductPriceService::priceWithDiscount($variety, $marketPrice, $vatStatus, $vat);
    }

    public static function priceWithoutDiscount($variety, $marketPrice, $vatStatus, $vat = 0.09)
    {
        return ProductPriceService::priceWithoutDiscount($variety, $marketPrice, $vatStatus, $vat);
    }

    public static function purchasePrice($object, $marketPrice, $count = 1)
    {
        return ProductPriceService::purchasePrice($object, $marketPrice, $count);
    }

    public static function getImage($variety)
    {
        // Use the collection's min method to find the image with the minimum order
        $minOrderImage = $variety->images->sortBy('pivot.orders')->first();

        // Return the image if found
        return $minOrderImage->image;
    }
}
