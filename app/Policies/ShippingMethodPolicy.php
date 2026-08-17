<?php

namespace App\Policies;

class ShippingMethodPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'shipping';
    }
}
