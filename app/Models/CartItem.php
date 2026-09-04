<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'count' => 'integer'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variety()
    {
        return $this->belongsTo(Variety::class);
    }
}
