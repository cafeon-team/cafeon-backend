<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerMenuController extends Controller
{
    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'is_available' => ['nullable', 'boolean'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $menus = Menu::query()->with('category:id,store_id,name,sort_order,is_active')
            ->where('store_id', $store->id)
            ->when(isset($validated['category_id']), fn ($query) => $query->where('category_id', $validated['category_id']))
            ->when(array_key_exists('is_available', $validated), fn ($query) => $query->where('is_available', $validated['is_available']))
            ->when($validated['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%")))
            ->orderBy('category_id')->orderBy('id')
            ->paginate($validated['per_page'] ?? 30);

        return response()->json([
            'categories' => $store->menuCategories()->orderBy('sort_order')->orderBy('id')->get(),
            'menus' => $menus,
        ]);
    }

    public function storeCategory(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('menu_categories')->where('store_id', $store->id)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'message' => '메뉴 카테고리가 등록되었습니다.',
            'category' => $store->menuCategories()->create($validated),
        ], 201);
    }

    public function updateCategory(Request $request, MenuCategory $category): JsonResponse
    {
        $category->loadMissing('store');
        $this->authorizeStore($request, $category->store);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('menu_categories')->where('store_id', $category->store_id)->ignore($category->id)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $category->update($validated);

        return response()->json(['message' => '메뉴 카테고리가 수정되었습니다.', 'category' => $category->fresh()]);
    }

    public function destroyCategory(Request $request, MenuCategory $category): JsonResponse
    {
        $category->loadMissing('store');
        $this->authorizeStore($request, $category->store);
        $category->delete();

        return response()->json(status: 204);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $validated = $this->validateMenu($request, $store);
        $menu = $store->menus()->create($validated);

        return response()->json([
            'message' => '메뉴가 등록되었습니다.',
            'menu' => $menu->load('category:id,store_id,name'),
        ], 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $menu->loadMissing('store');
        $this->authorizeStore($request, $menu->store);
        $menu->update($this->validateMenu($request, $menu->store, true));

        return response()->json([
            'message' => '메뉴가 수정되었습니다.',
            'menu' => $menu->fresh()->load('category:id,store_id,name'),
        ]);
    }

    public function updateAvailability(Request $request, Menu $menu): JsonResponse
    {
        $menu->loadMissing('store');
        $this->authorizeStore($request, $menu->store);
        $validated = $request->validate(['is_available' => ['required', 'boolean']]);
        $menu->update($validated);

        return response()->json([
            'message' => $menu->is_available ? '메뉴 판매를 시작했습니다.' : '메뉴를 품절 처리했습니다.',
            'menu' => $menu->fresh(),
        ]);
    }

    public function destroy(Request $request, Menu $menu): JsonResponse
    {
        $menu->loadMissing('store');
        $this->authorizeStore($request, $menu->store);
        $menu->delete();

        return response()->json(status: 204);
    }

    private function validateMenu(Request $request, Store $store, bool $updating = false): array
    {
        $validated = $request->validate([
            'category_id' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'integer', 'exists:menu_categories,id'],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => [$updating ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:10000000'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
            abort_unless(MenuCategory::whereKey($validated['category_id'])->where('store_id', $store->id)->exists(), 422, '다른 매장의 카테고리는 사용할 수 없습니다.');
        }

        return $validated;
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) $user->role) === 'ADMIN';
        $isManager = $store->members()->where('user_id', $user->id)->where('is_active', true)
            ->whereIn('role', ['OWNER', 'MANAGER'])->exists();

        abort_unless($isAdmin || $isManager, 403, '메뉴를 관리할 권한이 없습니다.');
    }
}
