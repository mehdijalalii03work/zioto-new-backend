<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Blog\Database\Factories\BlogTagFactory;

class BlogTag extends Model
{
    /** @use HasFactory<BlogTagFactory> */
    use HasFactory;

    protected $table = 'blog_tags';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function newFactory(): BlogTagFactory
    {
        return BlogTagFactory::new();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag', 'tag_id', 'post_id');
    }
}
