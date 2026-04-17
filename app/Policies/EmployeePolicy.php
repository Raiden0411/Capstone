<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->tenant_id === $employee->tenant_id;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->tenant_id === $employee->tenant_id;
    }

    public function delete(User $user, Employee $employee): bool
    {
        // A tenant cannot delete themselves if they are listed as an employee
        if ($user->id === $employee->user_id) {
            return false;
        }
        return $user->tenant_id === $employee->tenant_id;
    }
}