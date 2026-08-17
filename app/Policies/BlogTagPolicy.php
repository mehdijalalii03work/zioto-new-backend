<?php

namespace App\Policies;

class BlogTagPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'blog-tag';
    }
}
