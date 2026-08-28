<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Store;
use App\Services\OwnerStoreAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerMenuController extends Controller
{
    public function __construct(private readonly OwnerStoreAccessService $storeAccess) {}

    public function indexMine(Request $request): JsonResponse
    {
        return $this->index($request, $this->storeAccess->primary($request->user()), true);
    }

    public function storeMine(Request $request): JsonResponse
    {
        return $this->store($request, $this->storeAccess->primary($request->user()));
    }

    public function storeMineCategory(Request $request): JsonResponse
    {
        return $this->storeCategory($request, $this->storeAccess->primary($request->user()));
    }

    public function index(Request $request, Store $store, bool $flattenCategory = false): JsonResponse
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

        // The mobile owner UI treats `category` as a string. Preserve the
        // relation-shaped legacy endpoint while flattening the ID-less endpoint.
        if ($flattenCategory) {
            $menus->getCollection()->each(function (Menu $menu): void {
                $category = $menu->getRelation('category');

                $menu->setAttribute('category_detail', $category);
                $menu->unsetRelation('category');
                $menu->setAttribute('category', $category?->name);
                $menu->setAttribute('category_name', $category?->name);
            });
        }

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
        abort_if($validated['is_available'] && $menu->stock_quantity === 0, 422, '재고가 0개인 메뉴는 판매를 시작할 수 없습니다.');
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
        $this->normalizeMenuImage($request);

        if (! $request->has('category_id') && $request->filled('category')) {
            $categoryName = trim((string) $request->input('category'));
            $category = $store->menuCategories()->firstOrCreate(
                ['name' => $categoryName],
                ['sort_order' => (int) $store->menuCategories()->max('sort_order') + 1, 'is_active' => true],
            );
            $request->merge(['category_id' => $category->id]);
        }
        if (! $request->has('image_url') && $request->filled('image')) {
            $request->merge(['image_url' => $request->input('image')]);
        }
        if (! $request->has('is_available') && $request->has('soldOut')) {
            $request->merge(['is_available' => ! $request->boolean('soldOut')]);
        }
        if (! $request->has('stock_quantity')) {
            foreach (['stockQuantity', 'stock', 'quantity'] as $stockAlias) {
                if ($request->has($stockAlias)) {
                    $request->merge(['stock_quantity' => $request->input($stockAlias)]);
                    break;
                }
            }
        }

        $validated = $request->validate([
            'category_id' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'integer', 'exists:menu_categories,id'],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => [$updating ? 'sometimes' : 'required', 'numeric', 'min:1', 'max:10000000'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_available' => ['sometimes', 'boolean'],
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        if (array_key_exists('stock_quantity', $validated)) {
            if ($validated['stock_quantity'] === 0) {
                $validated['is_available'] = false;
            } elseif ($validated['stock_quantity'] !== null && ! array_key_exists('is_available', $validated)) {
                $validated['is_available'] = true;
            }
        }

        if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
            abort_unless(MenuCategory::whereKey($validated['category_id'])->where('store_id', $store->id)->exists(), 422, '다른 매장의 카테고리는 사용할 수 없습니다.');
        }

        return $validated;
    }

    private function normalizeMenuImage(Request $request): void
    {
        $value = $request->input('image_url', $request->input('image'));
        if (! is_string($value) || ! str_starts_with($value, 'data:image/')) {
            return;
        }

        if (! preg_match('/^data:(image\/(?:jpeg|png|webp|gif));base64,([A-Za-z0-9+\/=\r\n]+)$/', $value, $matches)) {
            throw ValidationException::withMessages([
                'image_url' => ['지원하지 않는 메뉴 이미지 형식입니다.'],
            ]);
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'image_url' => ['메뉴 이미지는 5MB 이하여야 합니다.'],
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (! isset($extensions[$mime]) || $mime !== $matches[1]) {
            throw ValidationException::withMessages([
                'image_url' => ['메뉴 이미지 파일이 올바르지 않습니다.'],
            ]);
        }

        $path = 'menu-images/'.Str::uuid().'.'.$extensions[$mime];
        Storage::disk('public')->put($path, $contents);
        $url = Storage::disk('public')->url($path);
        $request->merge(['image_url' => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url)]);
        $request->request->remove('image');
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $this->storeAccess->authorize($request->user(), $store);
    }
}
