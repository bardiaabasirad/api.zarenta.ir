<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class Variety extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->belongsToMany(ProductImage::class)->withPivot('orders');
    }

    public function image()
    {
        return $this->belongsTo(ProductImage::class)->latest();
    }

    public function log()
    {
        return $this->hasMany(VarietyLog::class, 'variety_id');
    }

    public function updateWithLogging($attributes, $cause = null)
    {
        // Retrieve the original attribute values of the model before any changes
        $original = $this->getOriginal();

        // Set the new attribute values on the model, but do not save yet
        $this->fill($attributes);

        // Determine which attributes have been modified since the last sync
        $dirty = $this->getDirty();

        // Save the new attribute values to the database
        $this->update($attributes);

        // Calculate which attributes have actually changed after the update
        // by comparing the dirty attributes with the original ones
        $changedAttributes = array_intersect_key($original, $dirty);

        // Get the changes and exclude 'updated_at'
        $changes = Arr::except($this->getChanges(), ['updated_at']);

        // If any attributes have been changed, proceed to log the changes
        if ($this->wasChanged()) {
            // Create a new log entry with the broker's ID, the ID and class type of the user who made the changes,
            // the new values of the changed attributes, the original values of those attributes, and the description of the changes
            VarietyLog::create([
                'variety_id' => $this->id,
                'log_by' => Auth::id(),
                'new_values' => $changes,
                'old_values' => $changedAttributes,
                'details' => $cause? ['cause' => $cause]:null,
            ]);
        }
    }
}
