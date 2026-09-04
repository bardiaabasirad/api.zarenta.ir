<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Directory extends Model
{
    use HasFactory;

    protected $fillable = ['slug','title','description','status'];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function sections() {
        return $this->morphMany(Section::class, 'sectionable');
    }
}
