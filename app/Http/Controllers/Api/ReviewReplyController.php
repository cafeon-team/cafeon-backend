<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewReplyController extends Controller
{
    public function store(Request $request, Review $review): JsonResponse
    {
        $review->loadMissing('store');
        $this->authorizeStore($request, $review->store);
        $validated = $this->validateContent($request);

        $reply = DB::transaction(function () use ($request, $review, $validated) {
            $lockedReview = Review::query()->lockForUpdate()->findOrFail($review->id);
            abort_if($lockedReview->reply()->exists(), 422, '이미 사장님 답글이 등록된 리뷰입니다.');

            return $lockedReview->reply()->create([
                'author_id' => $request->user()->id,
                'content' => $validated['content'],
            ]);
        });

        return response()->json([
            'message' => '사장님 답글이 등록되었습니다.',
            'reply' => $reply->load('author:id,name'),
        ], 201);
    }

    public function update(Request $request, ReviewReply $reply): JsonResponse
    {
        $reply->loadMissing('review.store');
        $this->authorizeStore($request, $reply->review->store);
        $validated = $this->validateContent($request);
        $reply->update(['content' => $validated['content']]);

        return response()->json([
            'message' => '사장님 답글이 수정되었습니다.',
            'reply' => $reply->fresh()->load('author:id,name'),
        ]);
    }

    public function destroy(Request $request, ReviewReply $reply): JsonResponse
    {
        $reply->loadMissing('review.store');
        $this->authorizeStore($request, $reply->review->store);
        $reply->delete();

        return response()->json(status: 204);
    }

    private function validateContent(Request $request): array
    {
        return $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) $user->role) === 'ADMIN';
        $isManager = $store->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', ['OWNER', 'MANAGER'])
            ->exists();

        abort_unless($isAdmin || $isManager, 403, '리뷰에 답글을 작성할 권한이 없습니다.');
    }
}
