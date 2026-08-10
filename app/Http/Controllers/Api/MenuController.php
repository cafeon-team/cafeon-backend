<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:menu_categories,id'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'name'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $menus = Menu::query()
            ->with('category:id,store_id,name,sort_order,is_active')
            ->where('store_id', $store->id)
            ->where('is_available', true)
            ->when($validated['keyword'] ?? null, function (Builder $query, string $keyword) {
                $query->where(function (Builder $query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when($validated['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId));

        match ($validated['sort'] ?? 'recommended') {
            'price_asc' => $menus->orderBy('price'),
            'price_desc' => $menus->orderByDesc('price'),
            'name' => $menus->orderBy('name'),
            default => $menus->orderBy('category_id')->orderBy('id'),
        };

        return response()->json($menus->paginate($validated['per_page'] ?? 20));
    }

    public function show(Menu $menu): JsonResponse
    {
        abort_unless($menu->is_available && $menu->store?->is_active, 404);

        return response()->json([
            'menu' => $menu->load(['store:id,name,slug', 'category:id,store_id,name']),
        ]);
    }
}
