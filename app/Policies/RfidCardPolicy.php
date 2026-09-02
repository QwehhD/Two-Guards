<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class RfidCardPolicy
{
    /**
     * Only admins may manage RFID cards. Karyawan get no access at all,
     * not even read-only viewing.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
