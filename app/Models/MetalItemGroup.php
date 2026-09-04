<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetalItemGroup extends Model
{
    protected $fillable = ['title', 'slug'];

    /** گروه شامل چند آیتم فلزی است */
    public function metalItems(): HasMany
    {
        return $this->hasMany(MetalItem::class, 'metal_item_group_id');
    }
}
