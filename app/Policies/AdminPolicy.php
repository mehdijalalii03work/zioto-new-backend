<?php

namespace App\Policies;

use App\Models\User;

abstract class AdminPolicy
{
    /**
     * Permission entity prefix (e.g. `product` → `product.view`).
     */
    abstract public static function entity(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(static::entity().'.view');
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(static::entity().'.create');
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasPermissionTo(static::entity().'.edit');
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->hasPermissionTo(static::entity().'.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->delete($user);
    }

    public function forceDelete(User $user, mixed $model = null): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->delete($user);
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return $this->delete($user, $model);
    }

    public function restoreAny(User $user): bool
    {
        return $this->delete($user);
    }

    public function reorder(User $user): bool
    {
        return $this->update($user);
    }
}
