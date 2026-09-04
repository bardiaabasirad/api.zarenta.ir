<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function metalTrader()
    {
        return $this->belongsTo(MetalTrader::class);
    }

    public function subscriptionFeatures()
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'sub_feature_pivot');
    }
}
