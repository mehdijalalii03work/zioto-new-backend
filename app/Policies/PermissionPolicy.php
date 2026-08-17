<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PermissionModel $permission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PermissionModel $permission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PermissionModel $permission): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return ! $permission->roles()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
