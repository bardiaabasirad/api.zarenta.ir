<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['created_by','sectionable_id','sectionable_type','order','status','description','show_on','show_only_available_products'];

    protected $casts = [
        'show_only_available_products'=> 'boolean'
    ];

    public function sectionable() {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
