<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Models\BlogPost;

class BlogController extends Controller
{
    private function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_convert_encoding($value, "UTF-8", "UTF-8");
    }

    private function formatPost(BlogPost $post): array
    {
        $content = $this->sanitize($post->content);

        $media = $post->getFirstMedia("featured-image");
        $imageUrl = $media ? $media->getUrl() : null;
        $responsiveUrls = null;
        $srcset = null;

        if ($media && $media->hasResponsiveImages()) {
            $responsiveUrls = [
                "original" => $media->getUrl(),
            ];
            foreach (["thumb", "small", "medium", "large"] as $conversion) {
                $url = $media->getUrl($conversion);
                if ($url && $url !== $media->getUrl()) {
                    $responsiveUrls[$conversion] = $url;
                }
            }
            $srcset = $media->getResponsiveImageUrls("srcset") ?? null;
        }

        return [
            "id" => $post->id,
            "title" => $this->sanitize($post->title),
            "slug" => $post->slug,
            "summary" => strip_tags(mb_substr($content ?? "", 0, 200, "UTF-8")),
            "content" => $content,
            "image" => $imageUrl,
            "image_responsive" => $responsiveUrls,
            "image_srcset" => $srcset,
            "status" => $post->status,
            "category" => $post->category ? [
                "id" => $post->category->id,
                "name" => $this->sanitize($post->category->name),
                "slug" => $post->category->slug,
            ] : null,
            "tags" => $post->tags->map(fn ($tag) => [
                "id" => $tag->id,
                "name" => $this->sanitize($tag->name),
                "slug" => $tag->slug,
            ]),
            "published_at" => $post->published_at?->toISOString(),
            "created_at" => $post->created_at->toISOString(),
        ];
    }

    public function posts(): JsonResponse
    {
        $query = BlogPost::query()
            ->with(["category:id,name,slug", "tags:id,name,slug", "media"])
            ->where("status", "published");

        if ($category = request()->input("category")) {
            $query->whereHas("category", fn ($q) => $q->where("slug", $category));
        }

        if ($tag = request()->input("tag")) {
            $query->whereHas("tags", fn ($q) => $q->where("slug", $tag));
        }

        $posts = $query->orderBy("sort_order")
            ->orderByDesc("published_at")
            ->paginate(perPage: 9);

        $posts->getCollection()->transform(fn (BlogPost $post) => $this->formatPost($post));

        return response()->json([
            "data" => $posts->items(),
            "meta" => [
                "current_page" => $posts->currentPage(),
                "last_page" => $posts->lastPage(),
                "per_page" => $posts->perPage(),
                "total" => $posts->total(),
            ],
        ]);
    }

    public function post(string $slugOrId): JsonResponse
    {
        $query = BlogPost::query()
            ->with(["category:id,name,slug", "tags:id,name,slug", "media"])
            ->where("status", "published");

        $post = is_numeric($slugOrId)
            ? $query->find($slugOrId)
            : $query->where("slug", $slugOrId)->first();

        if (! $post) {
            return response()->json(["message" => "مقاله یافت نشد", "error_code" => "POST_NOT_FOUND"], 404);
        }

        return response()->json([
            "data" => $this->formatPost($post),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = BlogCategory::query()
            ->where("is_active", true)
            ->withCount("posts")
            ->orderBy("sort_order")
            ->get()
            ->map(fn (BlogCategory $cat) => [
                "id" => $cat->id,
                "name" => $cat->name,
                "slug" => $cat->slug,
                "posts_count" => $cat->posts_count,
            ]);

        return response()->json(["data" => $categories]);
    }
}
