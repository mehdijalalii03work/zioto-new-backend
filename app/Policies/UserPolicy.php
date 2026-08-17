<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'customer';
    }

    public function update(User $user, mixed $model = null): bool
    {
        if (! $user->hasPermissionTo(static::entity().'.edit')) {
            return false;
        }

        if ($model instanceof User && $model->isStaff()) {
            return $user->isAdmin();
        }

        return true;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if ($model instanceof User && $model->is($user)) {
            return false;
        }

        if (! $user->hasPermissionTo(static::entity().'.delete')) {
            return false;
        }

        if (! $model instanceof User || ! $model->isStaff()) {
            return true;
        }

        if ($model->isAdmin() && $model->isLastAdmin()) {
            return false;
        }

        return $user->isAdmin();
    }
}
