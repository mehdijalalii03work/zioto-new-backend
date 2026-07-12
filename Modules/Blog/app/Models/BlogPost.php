<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Blog\Database\Factories\BlogPostFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BlogPost extends Model implements HasMedia
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $table = 'blog_posts';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'content',
        'image',
        'status',
        'sort_order',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): BlogPostFactory
    {
        return BlogPostFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured-image')
            ->useDisk('public')
            ->singleFile()
            ->withResponsiveImages();

        $this->addMediaCollection('content-images')
            ->useDisk('public');
    }
}
