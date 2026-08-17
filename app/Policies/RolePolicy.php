<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, RoleModel $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, RoleModel $role): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, RoleModel $role): bool
    {
        if ($role->name === Role::Admin->value) {
            return false;
        }

        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
