<?php

namespace App\Enums;

enum StockMode
{
    case Accumulate; // انباشت ساده
    case Rebalance;  // نگه‌داشتن بین ۳ تا ۲۰
}
