<?php

namespace App\Policies;

class BlogCategoryPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'blog-category';
    }
}
