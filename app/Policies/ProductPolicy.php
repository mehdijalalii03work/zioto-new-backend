<?php

namespace App\Policies;

use App\Models\ProductInventoryAudit;
use App\Models\SerialImportRow;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Product\Models\Product;

class ProductPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'product';
    }

    public function delete(User $user, mixed $model = null): bool|Response
    {
        if (! $user->hasPermissionTo(static::entity().'.delete')) {
            return false;
        }

        // Products with serial history are never deleted through the panel
        // (the database restrict guard is the second line of defence).
        if ($model instanceof Product && ($model->serials()->exists() || $model->manualReservations()->exists() || SerialImportRow::where('product_id', $model->id)->exists() || ProductInventoryAudit::where('product_id', $model->id)->exists())) {
            return Response::deny('محصول دارای تاریخچه موجودی، ایمپورت یا تغییرات انبار قابل حذف نیست.');
        }

        return true;
    }
}
