<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable implements LaratrustUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRolesAndPermissions;

    protected $guarded = ['id'];
    protected $hidden = ['password'];

    public function orderLogs()
    {
        return $this->morphMany(OrderLog::class, 'loggable');
    }

    public function isActive()
    {
        return $this->status == 'active';
    }

    public function metalOrders()
    {
        return $this->morphMany(MetalOrder::class, 'created');
    }
}
