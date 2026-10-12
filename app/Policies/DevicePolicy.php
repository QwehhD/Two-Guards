<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class DevicePolicy
{
    /**
     * Only admins may switch a device between auto and manual: the mode
     * decides whether a registered card opens the gate without anyone
     * approving it.
     */
    public function update(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
