<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckedNationalCode extends Model
{
    use HasFactory;

    protected $fillable = ['phone','national_code','status','updated_at'];
}
