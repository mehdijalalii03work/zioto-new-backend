<?php

namespace App\Policies;

class BrandPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'brand';
    }
}
