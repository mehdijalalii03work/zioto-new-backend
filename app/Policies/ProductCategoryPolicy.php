<?php

namespace App\Policies;

class ProductCategoryPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'category';
    }
}
