<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['unread_only' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        $rows = $request->user()->userNotifications()->when($data['unread_only'] ?? false, fn ($q) => $q->whereNull('read_at'))->latest()->paginate($data['per_page'] ?? 30);

        return response()->json($rows);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['message' => '알림을 읽음 처리했습니다.', 'notification' => $notification->fresh()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => '모든 알림을 읽음 처리했습니다.', 'updated_count' => $count]);
    }

    public function destroy(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->delete();

        return response()->json(status: 204);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $count = $request->user()->userNotifications()->delete();

        return response()->json(['message' => '모든 알림을 삭제했습니다.', 'deleted_count' => $count]);
    }
}
