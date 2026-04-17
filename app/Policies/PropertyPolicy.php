<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function view(User $user, Property $property): bool
    {
        return $user->tenant_id === $property->tenant_id;
    }

    public function update(User $user, Property $property): bool
    {
        return $user->tenant_id === $property->tenant_id;
    }

    public function delete(User $user, Property $property): bool
    {
        // Don't allow deletion if there are active bookings
        if ($property->bookings()->where('status', '!=', 'completed')->exists()) {
            return false;
        }
        return $user->tenant_id === $property->tenant_id;
    }
}