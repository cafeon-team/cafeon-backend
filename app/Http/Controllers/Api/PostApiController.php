<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Store;
use App\Models\Tag;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostApiController extends Controller
{
    public function __construct(private readonly ImageStorageService $images)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $posts = Post::query()
            ->with(['store:id,name,slug', 'category:id,name,slug', 'author:id,name'])
            ->withCount(['comments', 'likes'])
            ->where('status', 'PUBLISHED')
            ->when(isset($validated['store_id']), fn ($query) => $query->where('store_id', $validated['store_id']))
            ->when(isset($validated['category']), function ($query) use ($validated) {
                $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $validated['category']));
            })
            ->when(isset($validated['tag']), function ($query) use ($validated) {
                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('slug', $validated['tag']));
            })
            ->when(isset($validated['keyword']), function ($query) use ($validated) {
                $keyword = $validated['keyword'];
                $query->where(fn ($search) => $search
                    ->where('title', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%"));
            })
            ->latest('published_at')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json($posts);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()
            ->with([
                'store:id,name,slug',
                'category:id,name,slug',
                'author:id,name',
                'images',
                'tags:id,name,slug',
            ])
            ->withCount(['comments', 'likes'])
            ->where('slug', $slug)
            ->where('status', 'PUBLISHED')
            ->firstOrFail();

        $post->increment('view_count');

        return response()->json(PostResource::make($post)->resolve());
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $store = Store::findOrFail($validated['store_id']);
        abort_unless($store->is_active, 422, '비활성 매장에는 게시글을 등록할 수 없습니다.');
        $this->authorize('createForStore', [Post::class, $store]);

        $category = PostCategory::findOrFail($validated['category_id']);
        abort_unless($category->store_id === $store->id && $category->is_active, 422, '해당 매장에서 사용할 수 없는 카테고리입니다.');

        $tagIds = $validated['tag_ids'] ?? [];
        if ($tagIds !== []) {
            $validTagCount = Tag::where('store_id', $store->id)->whereIn('id', $tagIds)->count();
            abort_unless($validTagCount === count($tagIds), 422, '다른 매장의 태그가 포함되어 있습니다.');
        }

        $post = DB::transaction(function () use ($request, $validated, $store, $tagIds) {
            $status = $validated['status'];
            $post = Post::create([
                'store_id' => $store->id,
                'category_id' => $validated['category_id'],
                'author_id' => $request->user()->id,
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($store, $validated['slug'] ?? $validated['title']),
                'summary' => $validated['summary'] ?? null,
                'content' => $validated['content'],
                'thumbnail_url' => $validated['thumbnail_url'] ?? null,
                'status' => $status,
                'view_count' => 0,
                'scheduled_at' => $status === 'SCHEDULED' ? $validated['scheduled_at'] : null,
                'published_at' => $status === 'PUBLISHED' ? now() : null,
            ]);

            foreach (array_values($validated['images'] ?? []) as $index => $image) {
                $post->images()->create([
                    'image_url' => $image['image_url'],
                    'alt_text' => $image['alt_text'] ?? null,
                    'sort_order' => $image['sort_order'] ?? $index,
                ]);
            }

            $post->tags()->sync($tagIds);

            return $post;
        });

        return response()->json([
            'message' => '게시글을 등록했습니다.',
            'post' => PostResource::make($post->load(['store:id,name,slug', 'category:id,name,slug', 'author:id,name', 'images', 'tags:id,name,slug']))->resolve(),
        ], 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);
        $validated = $request->validated();

        if (isset($validated['category_id'])) {
            $category = PostCategory::findOrFail($validated['category_id']);
            abort_unless($category->store_id === $post->store_id && $category->is_active, 422, '해당 매장에서 사용할 수 없는 카테고리입니다.');
        }

        $tagIds = $validated['tag_ids'] ?? null;
        if ($tagIds !== null) {
            $validTagCount = Tag::where('store_id', $post->store_id)->whereIn('id', $tagIds)->count();
            abort_unless($validTagCount === count($tagIds), 422, '다른 매장의 태그가 포함되어 있습니다.');
        }

        $oldImageUrls = array_key_exists('images', $validated) ? $post->images()->pluck('image_url')->all() : [];
        $oldThumbnailUrl = array_key_exists('thumbnail_url', $validated) && $validated['thumbnail_url'] !== $post->thumbnail_url
            ? $post->thumbnail_url
            : null;

        $post = DB::transaction(function () use ($post, $validated, $tagIds) {
            $attributes = collect($validated)->except(['tag_ids', 'images'])->all();
            if (isset($attributes['slug'])) {
                $attributes['slug'] = $this->uniqueSlug($post->store, $attributes['slug'], $post->id);
            }
            if (isset($attributes['status'])) {
                $attributes['scheduled_at'] = $attributes['status'] === 'SCHEDULED' ? ($attributes['scheduled_at'] ?? null) : null;
                $attributes['published_at'] = $attributes['status'] === 'PUBLISHED' ? ($post->published_at ?? now()) : null;
            }
            $post->update($attributes);

            if (array_key_exists('images', $validated)) {
                $post->images()->delete();
                foreach (array_values($validated['images']) as $index => $image) {
                    $post->images()->create([
                        'image_url' => $image['image_url'],
                        'alt_text' => $image['alt_text'] ?? null,
                        'sort_order' => $image['sort_order'] ?? $index,
                    ]);
                }
            }
            if ($tagIds !== null) {
                $post->tags()->sync($tagIds);
            }

            return $post;
        });

        $this->images->deleteLocalUrls([...$oldImageUrls, $oldThumbnailUrl]);

        return response()->json([
            'message' => '게시글을 수정했습니다.',
            'post' => PostResource::make($post->load(['category:id,name,slug', 'images', 'tags:id,name,slug']))->resolve(),
        ]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $imageUrls = $post->images()->pluck('image_url')->push($post->thumbnail_url)->all();
        $post->delete();
        $this->images->deleteLocalUrls($imageUrls);

        return response()->json(status: 204);
    }

    private function uniqueSlug(Store $store, string $source, ?int $ignorePostId = null): string
    {
        $base = Str::slug($source) ?: 'post-'.Str::lower(Str::random(8));
        $slug = $base;
        $sequence = 2;

        while (Post::withTrashed()->where('store_id', $store->id)->where('slug', $slug)
            ->when($ignorePostId, fn ($query) => $query->whereKeyNot($ignorePostId))->exists()) {
            $slug = $base.'-'.$sequence++;
        }

        return $slug;
    }
}
