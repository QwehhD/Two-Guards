<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AccessLog;
use App\Models\User;

class AccessLogPolicy
{
    /**
     * Both admin and karyawan may view access history.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Karyawan], true);
    }

    /**
     * Both admin and karyawan may approve/reject a manual-mode scan.
     * Custom policy methods (not one of Laravel's default CRUD abilities)
     * are called the same way: $user->can('approve', $accessLog) or
     * Gate::authorize('approve', $accessLog).
     */
    public function approve(User $user, AccessLog $accessLog): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Karyawan], true);
    }

    public function reject(User $user, AccessLog $accessLog): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Karyawan], true);
    }
}
