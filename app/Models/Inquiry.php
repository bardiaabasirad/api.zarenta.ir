<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'fields' => 'array',
        'response' => 'array',
    ];

    public function metalTraders()
    {
        return $this->belongsToMany(MetalTrader::class);
    }
}
