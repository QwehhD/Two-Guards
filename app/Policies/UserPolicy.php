<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Only admins may list/manage employee accounts.
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

    /**
     * Accounts are deleted only by an admin, on the Akun page, and never the
     * admin's own: the profile page has no delete. That also covers "never
     * the last admin" here — an admin deleting another admin leaves at least
     * themselves — and User::booted() guards that rule for every other path.
     */
    public function delete(User $user, User $model): Response
    {
        if ($user->role !== UserRole::Admin) {
            return Response::deny();
        }

        if ($user->is($model)) {
            return Response::deny('Anda tidak bisa menghapus akun Anda sendiri.');
        }

        return Response::allow();
    }
}
