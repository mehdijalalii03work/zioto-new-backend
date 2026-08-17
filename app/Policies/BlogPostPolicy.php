<?php

namespace App\Policies;

class BlogPostPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'blog-post';
    }
}
