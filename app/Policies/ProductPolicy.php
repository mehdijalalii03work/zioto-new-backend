<?php

namespace App\Policies;

class ProductPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'product';
    }
}
