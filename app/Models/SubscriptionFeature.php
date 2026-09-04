<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionFeature extends Model
{
    use HasFactory;

    public function subscriptions()
    {
        return $this->belongsToMany(Subscription::class, 'sub_feature_pivot');
    }
}
