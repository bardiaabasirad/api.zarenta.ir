<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MarketPrice extends Model
{
    use HasFactory;

    // Use this in your MarketPrice model's save method or in the logic where you update the market price

    public function save(array $options = [])
    {
        // Call the original save method to store the model
        parent::save($options);

        // After saving, forget the cached market price
        Cache::forget('market_price.latest');
        Cache::forget('market_price.previous_day');
        Cache::forget('market_price.previous');
    }

    // Now, when you call the last() method, it will fetch fresh data and repopulate the cache

}
