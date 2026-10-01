<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasPermissionAtTenant('manage events')
            || $user->hasPermissionAtTenant('view events');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasPermissionAtTenant('manage events');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }
}