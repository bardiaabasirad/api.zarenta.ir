<?php

namespace App\Services\Hamtala;

class HamtalaConstants
{
    // receive-order response status
    public const ORDER_PENDING     = 3; // دریافت شد، در انتظار تایید ادمین
    public const ORDER_NO_PRODUCT  = 2; // محصول یافت نشد
    public const ORDER_FAILED      = 0; // خطا یا رد

    // confirmation response status
    public const CONFIRM_SUCCESS   = 1;
    public const CONFIRM_FAILED    = 0;

    // buy_or_sell
    public const BUY  = 1;
    public const SELL = 2;

    // order_type
    public const ORDER_TYPE_NORMAL = 1;
    public const ORDER_TYPE_OTHER  = 2;

    // product_type
    public const PRODUCT_TYPE_GOLD = 1;
    public const PRODUCT_TYPE_COIN = 2;

    // مقدار غیرفعال بودن یک سمت قیمت
    public const PRICE_DISABLED = -1;
}
