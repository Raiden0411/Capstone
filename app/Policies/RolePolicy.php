<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine if the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        // Nobody can edit the super-admin role
        return $role->name !== 'super-admin';
    }

    /**
     * Determine if the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        // Nobody can delete the super-admin role
        return $role->name !== 'super-admin';
    }
}