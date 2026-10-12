<?php

namespace App\Policies;

use App\Models\User;
use Modules\Order\Models\Order;

class OrderPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'order';
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if (! $user->hasPermissionTo(static::entity().'.delete')) {
            return false;
        }

        // An order holding live serials or capacity must be released first;
        // deleting it would orphan physical-bar history.
        if ($model instanceof Order && $model->hasActiveSerialHoldings()) {
            return false;
        }

        return true;
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return $user->hasPermissionTo(static::entity().'.delete');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermissionTo(static::entity().'.delete');
    }
}
