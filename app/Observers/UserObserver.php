<?php

namespace App\Observers;

use App\Events\UserUpdated;
use App\Models\User;

class UserObserver
{
    public function updated(User $user): void
    {
        event(new UserUpdated($user));
    }
}
