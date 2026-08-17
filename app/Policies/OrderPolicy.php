<?php

namespace App\Policies;

class OrderPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'order';
    }
}
