<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(User $user, Address $address)
    {
        return $user->id === $address->user_id;
    }

    public function update(User $user, Address $address)
    {
        return $user->id === $address->user_id;
    }

    public function delete(User $user, Address $address)
    {
        return $user->id === $address->user_id;
    }
}
