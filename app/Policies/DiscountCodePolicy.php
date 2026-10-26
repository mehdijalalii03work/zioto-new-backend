<?php

namespace App\Policies;

class DiscountCodePolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'discount-code';
    }
}
