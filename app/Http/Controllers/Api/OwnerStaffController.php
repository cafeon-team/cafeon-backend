<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerStaffController extends Controller
{
    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);

        return response()->json(['data' => $store->members()->with('user:id,name,email,phone,is_active')->orderBy('role')->get()]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email'], 'role' => ['required', Rule::in(['MANAGER', 'STAFF'])]]);
        $user = User::where('email', $data['email'])->firstOrFail();
        $member = $store->members()->updateOrCreate(['user_id' => $user->id], ['role' => $data['role'], 'is_active' => true]);

        return response()->json(['message' => '직원이 등록되었습니다.', 'member' => $member->load('user:id,name,email,phone')], 201);
    }

    public function update(Request $request, StoreMember $member): JsonResponse
    {
        $this->authorizeOwner($request, $member->store);
        abort_if($member->role === 'OWNER', 422, '매장 소유자 권한은 변경할 수 없습니다.');
        $data = $request->validate(['role' => ['sometimes', Rule::in(['MANAGER', 'STAFF'])], 'is_active' => ['sometimes', 'boolean']]);
        $member->update($data);

        return response()->json(['message' => '직원 정보가 수정되었습니다.', 'member' => $member->fresh()->load('user:id,name,email,phone')]);
    }

    public function destroy(Request $request, StoreMember $member): JsonResponse
    {
        $this->authorizeOwner($request, $member->store);
        abort_if($member->role === 'OWNER', 422, '매장 소유자는 삭제할 수 없습니다.');
        $member->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwner(Request $request, Store $store): void
    {
        $user = $request->user();
        $allowed = strtoupper((string) $user->role) === 'ADMIN' || $store->members()->where('user_id', $user->id)->where('is_active', true)->where('role', 'OWNER')->exists();
        abort_unless($allowed, 403, '직원을 관리할 권한이 없습니다.');
    }
}
