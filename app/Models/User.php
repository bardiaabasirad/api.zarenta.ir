<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'products_settings' => 'array',
    ];

    public function checkedNationalCodes()
    {
        return $this->hasMany(CheckedNationalCode::class, 'phone', 'phone');
    }

    public function orderLogs()
    {
        return $this->morphMany(OrderLog::class, 'loggable');
    }

    public function scopeCompletedRegistered($query)
    {
        return $query->whereIn('status', [UserStatus::ACTIVE, UserStatus::INACTIVE]);
    }

    public function updateWithLogging($attributes)
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
            UserLog::create([
                'user_id' => $this->id,
                'loggable_id' => Auth::id(),
                'loggable_type' => get_class(auth()->user()),
                'new_values' => $changes,
                'old_values' => $changedAttributes,
            ]);
        }
    }
}
