<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['product_id','variety_id','product','count'];

    public function variety()
    {
        return $this->belongsTo(Variety::class);
    }

    protected $casts = [
        'product' => 'json'
    ];
}
