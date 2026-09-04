<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = ['title','images'];

    protected $casts = [
        'images' => 'json'
    ];

    public function sections() {
        return $this->morphMany(Section::class, 'sectionable');
    }
}
