<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage events');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage events');
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