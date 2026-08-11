<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostCategoryResource;
use App\Http\Resources\TagResource;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Store;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlogTaxonomyController extends Controller
{
    public function categories(Store $store): JsonResponse
    {
        return response()->json(PostCategoryResource::collection($store->postCategories()->orderBy('sort_order')->orderBy('name')->get())->resolve());
    }

    public function storeCategory(Request $request, Store $store): JsonResponse
    {
        $this->authorize('manageBlog', [Post::class, $store]);
        $validated = $this->validateCategory($request, $store);

        return response()->json(PostCategoryResource::make($store->postCategories()->create($validated))->resolve(), 201);
    }

    public function updateCategory(Request $request, PostCategory $category): JsonResponse
    {
        $this->authorize('manageBlog', [Post::class, $category->store]);
        $category->update($this->validateCategory($request, $category->store, $category));

        return response()->json(PostCategoryResource::make($category->fresh())->resolve());
    }

    public function destroyCategory(PostCategory $category): JsonResponse
    {
        $this->authorize('manageBlog', [Post::class, $category->store]);
        abort_if($category->posts()->exists(), 422, '게시글이 연결된 카테고리는 삭제할 수 없습니다.');
        $category->delete();

        return response()->json(status: 204);
    }

    public function tags(Store $store): JsonResponse
    {
        return response()->json(TagResource::collection($store->tags()->orderBy('name')->get())->resolve());
    }

    public function storeTag(Request $request, Store $store): JsonResponse
    {
        $this->authorize('manageBlog', [Post::class, $store]);
        $validated = $this->validateTag($request, $store);

        return response()->json(TagResource::make($store->tags()->create($validated))->resolve(), 201);
    }

    public function updateTag(Request $request, Tag $tag): JsonResponse
    {
        $this->authorize('manageBlog', [Post::class, $tag->store]);
        $tag->update($this->validateTag($request, $tag->store, $tag));

        return response()->json(TagResource::make($tag->fresh())->resolve());
    }

    public function destroyTag(Tag $tag): JsonResponse
    {
        $this->authorize('manageBlog', [Post::class, $tag->store]);
        $tag->posts()->detach();
        $tag->delete();

        return response()->json(status: 204);
    }

    private function validateCategory(Request $request, Store $store, ?PostCategory $category = null): array
    {
        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:50'],
            'slug' => [$category ? 'sometimes' : 'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('post_categories')->where('store_id', $store->id)->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateTag(Request $request, Store $store, ?Tag $tag = null): array
    {
        return $request->validate([
            'name' => [$tag ? 'sometimes' : 'required', 'string', 'max:50'],
            'slug' => [$tag ? 'sometimes' : 'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tags')->where('store_id', $store->id)->ignore($tag?->id)],
        ]);
    }
}
