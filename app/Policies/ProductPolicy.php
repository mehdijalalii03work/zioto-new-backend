<?php

namespace App\Policies;

use App\Models\User;
use Modules\Product\Models\Product;

class ProductPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'product';
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if (! $user->hasPermissionTo(static::entity().'.delete')) {
            return false;
        }

        // Products with serial history are never deleted through the panel
        // (the database restrict guard is the second line of defence).
        if ($model instanceof Product && $model->serials()->exists()) {
            return false;
        }

        return true;
    }
}
