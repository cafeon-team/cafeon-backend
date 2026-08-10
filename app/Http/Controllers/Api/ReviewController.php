<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(Request $request, Store $store): JsonResponse
    {
        $reviews = Review::query()
            ->with(['user:id,name,profile_image_url', 'images', 'reply.author:id,name'])
            ->where('store_id', $store->id)
            ->where('status', 'VISIBLE')
            ->latest()
            ->paginate(min(50, max(1, $request->integer('per_page', 10))));

        return response()->json($reviews);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $validated = $this->validateReview($request, true);
        $order = null;
        if (! empty($validated['order_id'])) {
            $order = Order::findOrFail($validated['order_id']);
            abort_unless($order->user_id === $request->user()->id && $order->store_id === $store->id, 403);
            abort_if(Review::where('order_id', $order->id)->exists(), 422, '이미 리뷰를 작성한 주문입니다.');
        }

        $review = DB::transaction(function () use ($request, $store, $validated, $order) {
            $review = Review::create([
                'store_id' => $store->id,
                'user_id' => $request->user()->id,
                'order_id' => $order?->id,
                'rating' => $validated['rating'],
                'content' => $validated['content'],
                'is_verified_purchase' => $order !== null,
                'status' => 'VISIBLE',
            ]);
            $this->syncImages($review, $validated['image_urls'] ?? []);

            return $review;
        });

        return response()->json(['message' => '리뷰가 등록되었습니다.', 'review' => $review->load('images')], 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $validated = $this->validateReview($request, false);
        DB::transaction(function () use ($review, $validated) {
            $review->update(collect($validated)->only(['rating', 'content'])->all());
            if (array_key_exists('image_urls', $validated)) {
                $review->images()->delete();
                $this->syncImages($review, $validated['image_urls'] ?? []);
            }
        });

        return response()->json(['message' => '리뷰가 수정되었습니다.', 'review' => $review->fresh()->load('images')]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $review->delete();

        return response()->json(['message' => '리뷰가 삭제되었습니다.']);
    }

    private function validateReview(Request $request, bool $creating): array
    {
        return $request->validate([
            'order_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'exists:orders,id'],
            'rating' => [$creating ? 'required' : 'sometimes', 'integer', 'between:1,5'],
            'content' => [$creating ? 'required' : 'sometimes', 'string', 'max:3000'],
            'image_urls' => ['sometimes', 'array', 'max:5'],
            'image_urls.*' => ['url', 'max:500'],
        ]);
    }

    private function syncImages(Review $review, array $urls): void
    {
        foreach (array_values($urls) as $index => $url) {
            $review->images()->create(['image_url' => $url, 'sort_order' => $index]);
        }
    }
}
