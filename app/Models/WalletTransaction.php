<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $guarded = ['id'];

    public function metalOrder()
    {
        return $this->belongsTo(MetalOrder::class, 'metal_order_id');
    }
}
