<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerInventoryController extends Controller
{
    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $data = $request->validate(['low_stock' => ['nullable', 'boolean'], 'keyword' => ['nullable', 'string', 'max:100']]);
        $items = $store->inventories()->with('updater:id,name')->where('is_active', true)
            ->when($data['low_stock'] ?? false, fn ($q) => $q->whereColumn('quantity', '<=', 'low_stock_threshold'))
            ->when($data['keyword'] ?? null, fn ($q, $v) => $q->where('ingredient_name', 'like', "%{$v}%"))
            ->orderBy('ingredient_name')->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $data = $request->validate([
            'ingredient_name' => ['required', 'string', 'max:100', Rule::unique('inventories')->where('store_id', $store->id)],
            'quantity' => ['sometimes', 'numeric', 'min:0'], 'unit' => ['required', 'string', 'max:20'],
            'low_stock_threshold' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $data['created_by'] = $data['updated_by'] = $request->user()->id;
        $item = $store->inventories()->create($data);

        return response()->json(['message' => '재고 품목이 등록되었습니다.', 'inventory' => $item], 201);
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeStore($request, $inventory->store);
        $data = $request->validate([
            'ingredient_name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('inventories')->where('store_id', $inventory->store_id)->ignore($inventory->id)],
            'unit' => ['sometimes', 'required', 'string', 'max:20'], 'low_stock_threshold' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $inventory->update([...$data, 'updated_by' => $request->user()->id]);

        return response()->json(['message' => '재고 품목이 수정되었습니다.', 'inventory' => $inventory->fresh()]);
    }

    public function transact(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeStore($request, $inventory->store);
        $data = $request->validate([
            'type' => ['required', Rule::in(['STOCK_IN', 'STOCK_OUT', 'ADJUSTMENT', 'RETURN', 'WASTE'])],
            'quantity' => ['required_unless:type,ADJUSTMENT', 'nullable', 'numeric', 'gt:0'],
            'quantity_after' => ['required_if:type,ADJUSTMENT', 'nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        [$item, $transaction] = DB::transaction(function () use ($request, $inventory, $data) {
            $item = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);
            $before = (float) $item->quantity;
            $after = match ($data['type']) {
                'STOCK_IN', 'RETURN' => $before + (float) $data['quantity'],
                'STOCK_OUT', 'WASTE' => $before - (float) $data['quantity'],
                'ADJUSTMENT' => (float) $data['quantity_after'],
            };
            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => '현재 재고보다 많은 수량을 출고하거나 폐기할 수 없습니다.']);
            }
            $delta = $data['type'] === 'ADJUSTMENT' ? abs($after - $before) : (float) $data['quantity'];
            $item->update(['quantity' => $after, 'updated_by' => $request->user()->id]);
            $transaction = $item->transactions()->create([
                'created_by' => $request->user()->id, 'type' => $data['type'], 'quantity' => $delta,
                'quantity_before' => $before, 'quantity_after' => $after, 'reason' => $data['reason'] ?? null,
            ]);

            return [$item->fresh(), $transaction];
        });

        return response()->json(['message' => '재고 수량이 반영되었습니다.', 'inventory' => $item, 'transaction' => $transaction]);
    }

    public function transactions(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $data = $request->validate(['inventory_id' => ['nullable', 'integer'], 'type' => ['nullable', Rule::in(['STOCK_IN', 'STOCK_OUT', 'ADJUSTMENT', 'RETURN', 'WASTE'])], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        $rows = InventoryTransaction::query()->with(['inventory:id,store_id,ingredient_name,unit', 'creator:id,name'])
            ->whereHas('inventory', fn ($q) => $q->where('store_id', $store->id))
            ->when($data['inventory_id'] ?? null, fn ($q, $v) => $q->where('inventory_id', $v))
            ->when($data['type'] ?? null, fn ($q, $v) => $q->where('type', $v))->latest()->paginate($data['per_page'] ?? 30);

        return response()->json($rows);
    }

    public function destroy(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeStore($request, $inventory->store);
        $inventory->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $inventory->delete();

        return response()->json(status: 204);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $allowed = strtoupper((string) $user->role) === 'ADMIN' || $store->members()->where('user_id', $user->id)->where('is_active', true)->whereIn('role', ['OWNER', 'MANAGER'])->exists();
        abort_unless($allowed, 403, '재고를 관리할 권한이 없습니다.');
    }
}
