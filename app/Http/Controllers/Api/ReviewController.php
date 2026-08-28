<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Store;
use App\Models\UploadedImage;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    public function __construct(private readonly ImageStorageService $imageStorage) {}

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

        $review = DB::transaction(function () use ($request, $store, $validated) {
            $visit = $this->resolveCompletedVisit($request, $store, $validated);
            abort_if(Review::where('customer_visit_id', $visit->id)->exists(), 422, '이미 해당 방문에 대한 리뷰를 작성했습니다.');

            $review = Review::create([
                'store_id' => $store->id,
                'user_id' => $request->user()->id,
                'order_id' => $visit->order_id,
                'customer_visit_id' => $visit->id,
                'rating' => $validated['rating'],
                'content' => $validated['content'],
                'is_verified_purchase' => true,
                'status' => 'VISIBLE',
            ]);
            $this->syncImages($review, $request->user()->id, $validated['image_urls'] ?? []);

            return $review;
        });

        return response()->json(['message' => '리뷰가 등록되었습니다.', 'review' => $review->load('images')], 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $validated = $this->validateReview($request, false, $review);
        $oldImageUrls = $review->images()->pluck('image_url')->all();
        DB::transaction(function () use ($request, $review, $validated) {
            $review->update(collect($validated)->only(['rating', 'content'])->all());
            if (array_key_exists('image_urls', $validated)) {
                $review->images()->delete();
                UploadedImage::where('attached_type', 'review')->where('attached_id', $review->id)
                    ->update(['attached_type' => null, 'attached_id' => null]);
                $this->syncImages($review, $request->user()->id, $validated['image_urls'] ?? []);
            }
        });

        if (array_key_exists('image_urls', $validated)) {
            $this->deleteReleasedUploads($request->user()->id, array_diff($oldImageUrls, $validated['image_urls'] ?? []));
        }

        return response()->json(['message' => '리뷰가 수정되었습니다.', 'review' => $review->fresh()->load('images')]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $imageUrls = $review->images()->pluck('image_url')->all();
        DB::transaction(function () use ($review): void {
            $review->images()->delete();
            UploadedImage::where('attached_type', 'review')->where('attached_id', $review->id)
                ->update(['attached_type' => null, 'attached_id' => null]);
            $review->delete();
        });
        $this->deleteReleasedUploads($request->user()->id, $imageUrls);

        return response()->json(['message' => '리뷰가 삭제되었습니다.']);
    }

    private function validateReview(Request $request, bool $creating, ?Review $review = null): array
    {
        $validated = $request->validate([
            'customer_visit_id' => [$creating ? 'required_without_all:order_id,reservation_id' : 'prohibited', 'prohibits:order_id,reservation_id', 'integer', 'exists:customer_visits,id'],
            'order_id' => [$creating ? 'required_without_all:customer_visit_id,reservation_id' : 'prohibited', 'prohibits:customer_visit_id,reservation_id', 'integer', 'exists:orders,id'],
            'reservation_id' => [$creating ? 'required_without_all:customer_visit_id,order_id' : 'prohibited', 'prohibits:customer_visit_id,order_id', 'integer', 'exists:reservations,id'],
            'rating' => [$creating ? 'required' : 'sometimes', 'integer', 'between:1,5'],
            'content' => [$creating ? 'required' : 'sometimes', 'string', 'max:3000'],
            'image_urls' => ['sometimes', 'array', 'max:5'],
            'image_urls.*' => ['url', 'max:500', 'distinct'],
        ]);

        if (! array_key_exists('image_urls', $validated)) {
            return $validated;
        }

        $normalized = [];
        foreach ($validated['image_urls'] as $index => $url) {
            $path = $this->imageStorage->publicDiskPath($url);
            $upload = $path === null ? null : UploadedImage::query()
                ->where('user_id', $request->user()->id)
                ->where('disk', 'public')
                ->where('path', $path)
                ->where(function ($query) use ($review): void {
                    $query->whereNull('attached_type');
                    if ($review !== null) {
                        $query->orWhere(fn ($query) => $query
                            ->where('attached_type', 'review')->where('attached_id', $review->id));
                    }
                })->first();

            if ($upload === null || ! Storage::disk('public')->exists($upload->path)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "image_urls.{$index}" => ['본인이 업로드한 유효한 CafeOn 이미지만 리뷰에 첨부할 수 있습니다.'],
                ]);
            }
            $url = Storage::disk('public')->url($upload->path);
            $normalized[] = Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
        }
        $validated['image_urls'] = $normalized;

        return $validated;
    }

    private function resolveCompletedVisit(Request $request, Store $store, array $validated): CustomerVisit
    {
        if (! empty($validated['customer_visit_id'])) {
            $visit = CustomerVisit::query()->lockForUpdate()->findOrFail($validated['customer_visit_id']);
            abort_unless($visit->user_id === $request->user()->id && $visit->store_id === $store->id, 403);

            return $visit;
        }

        if (! empty($validated['order_id'])) {
            $order = Order::query()->lockForUpdate()->findOrFail($validated['order_id']);
            abort_unless($order->user_id === $request->user()->id && $order->store_id === $store->id, 403);
            abort_unless($order->status === 'COMPLETED', 422, '완료된 주문만 리뷰를 작성할 수 있습니다.');

            return CustomerVisit::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'user_id' => $order->user_id,
                    'store_id' => $order->store_id,
                    'type' => 'PURCHASE',
                    'visited_at' => $order->completed_at ?? now(),
                    'idempotency_key' => "order:{$order->id}:completed",
                ],
            );
        }

        $reservation = Reservation::query()->lockForUpdate()->findOrFail($validated['reservation_id']);
        abort_unless($reservation->user_id === $request->user()->id && $reservation->store_id === $store->id, 403);
        abort_unless($reservation->status === 'COMPLETED', 422, '방문 완료된 예약만 리뷰를 작성할 수 있습니다.');

        return CustomerVisit::firstOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'user_id' => $reservation->user_id,
                'store_id' => $reservation->store_id,
                'type' => 'RESERVATION',
                'visited_at' => $reservation->completed_at ?? now(),
                'idempotency_key' => "reservation:{$reservation->id}:completed",
            ],
        );
    }

    private function syncImages(Review $review, int $userId, array $urls): void
    {
        foreach (array_values($urls) as $index => $url) {
            $review->images()->create(['image_url' => $url, 'sort_order' => $index]);
            $path = $this->imageStorage->publicDiskPath($url);
            UploadedImage::where('user_id', $userId)->where('path', $path)->update([
                'attached_type' => 'review', 'attached_id' => $review->id,
            ]);
        }
    }

    private function deleteReleasedUploads(int $userId, iterable $urls): void
    {
        foreach ($urls as $url) {
            $path = $this->imageStorage->publicDiskPath($url);
            if ($path === null) {
                continue;
            }
            $upload = UploadedImage::where('user_id', $userId)->where('path', $path)
                ->whereNull('attached_type')->first();
            if ($upload !== null) {
                Storage::disk($upload->disk)->delete($upload->path);
                $upload->delete();
            }
        }
    }
}
