<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\OwnerStoreAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerStoreController extends Controller
{
    public function __construct(private readonly OwnerStoreAccessService $storeAccess) {}

    public function showMine(Request $request): JsonResponse
    {
        return $this->show($request, $this->primaryOwnerStore($request));
    }

    public function updateMine(Request $request): JsonResponse
    {
        return $this->update($request, $this->primaryOwnerStore($request));
    }

    public function updateMineBusinessStatus(Request $request): JsonResponse
    {
        return $this->updateBusinessStatus($request, $this->primaryOwnerStore($request));
    }

    public function updateMineLocation(Request $request): JsonResponse
    {
        $store = $this->primaryOwnerStore($request);
        $this->authorizeOwner($request, $store);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'detail_address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $store->fill($validated)->save();

        return response()->json([
            'message' => '지도 위치가 저장되었습니다.',
            'store' => $this->ownerProfile($store),
        ]);
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);

        return response()->json([
            'store' => $this->ownerProfile($store),
        ]);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);
        $this->normalizeProfilePayload($request);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'detail_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'business_hours_text' => ['nullable', 'string', 'max:255'],
            'thumbnail_url' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'reservation_enabled' => ['sometimes', 'boolean'],
            'business_info' => ['sometimes', 'nullable', 'array'],
            'business_info.business_registration_number' => ['nullable', 'string', 'max:30'],
            'business_info.representative_name' => ['nullable', 'string', 'max:100'],
            'business_info.company_name' => ['nullable', 'string', 'max:150'],
            'business_info.business_type' => ['nullable', 'string', 'max:100'],
            'business_info.business_item' => ['nullable', 'string', 'max:100'],
            'business_info.business_address' => ['nullable', 'string', 'max:255'],
            'business_hours' => ['sometimes', 'array', 'max:7'],
            'business_hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'business_hours.*.opening_time' => ['nullable', 'date_format:H:i'],
            'business_hours.*.closing_time' => ['nullable', 'date_format:H:i'],
            'business_hours.*.is_closed' => ['required', 'boolean'],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*.name' => ['required', 'string', 'max:50'],
            'tags.*.slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        foreach ($validated['business_hours'] ?? [] as $index => $hours) {
            if (! $hours['is_closed'] && (blank($hours['opening_time'] ?? null) || blank($hours['closing_time'] ?? null))) {
                throw ValidationException::withMessages([
                    "business_hours.{$index}.opening_time" => ['영업일에는 시작 시간과 종료 시간이 필요합니다.'],
                ]);
            }
            if (! $hours['is_closed'] && $hours['closing_time'] <= $hours['opening_time']) {
                throw ValidationException::withMessages([
                    "business_hours.{$index}.closing_time" => ['종료 시간은 시작 시간보다 늦어야 합니다.'],
                ]);
            }
        }

        $businessHours = $validated['business_hours'] ?? null;
        $tags = $validated['tags'] ?? null;
        unset($validated['business_hours'], $validated['tags']);

        if ($businessHours !== null) {
            $validated['business_hours_text'] = $this->summarizeBusinessHours($businessHours);
        }

        DB::transaction(function () use ($store, $validated, $businessHours, $tags): void {
            $store->fill($validated)->save();

            foreach ($businessHours ?? [] as $hours) {
                $store->businessHours()->updateOrCreate(
                    ['day_of_week' => $hours['day_of_week']],
                    [
                        'opening_time' => $hours['is_closed'] ? null : $hours['opening_time'],
                        'closing_time' => $hours['is_closed'] ? null : $hours['closing_time'],
                        'is_closed' => $hours['is_closed'],
                    ],
                );
            }

            if ($tags !== null) {
                $keptIds = collect($tags)->map(function (array $tag) use ($store): int {
                    $slug = $tag['slug'] ?? $this->stableTagSlug($tag['name']);

                    return $store->tags()->updateOrCreate(
                        ['slug' => $slug],
                        ['name' => $tag['name']],
                    )->id;
                });

                $store->tags()->whereNotIn('id', $keptIds)->get()->each(function ($tag): void {
                    $tag->posts()->detach();
                    $tag->delete();
                });
            }
        });

        return response()->json([
            'message' => '매장 정보가 수정되었습니다.',
            'store' => $this->ownerProfile($store),
        ]);
    }

    public function updateBusinessStatus(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);
        if (! $request->has('is_open')) {
            $value = $request->input('is_active', $request->input('status'));
            $normalized = match (strtoupper(trim((string) $value))) {
                '1', 'TRUE', 'OPEN', 'OPENED', 'OPERATING', '운영중', '영업중' => true,
                '0', 'FALSE', 'CLOSE', 'CLOSED', 'STOPPED', '운영종료', '영업종료', '마감' => false,
                default => $value,
            };
            $request->merge(['is_open' => $normalized]);
        }
        $validated = $request->validate(['is_open' => ['required', 'boolean']]);
        $store->forceFill(['is_open' => $validated['is_open']])->save();

        return response()->json([
            'message' => $store->is_open ? '영업 중으로 변경되었습니다.' : '영업 마감으로 변경되었습니다.',
            'store' => $store->fresh(),
        ]);
    }

    public function destroy(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);

        DB::transaction(function () use ($store): void {
            $store->members()->update(['is_active' => false]);
            $store->forceFill(['is_active' => false, 'is_open' => false])->save();
            $store->delete();
        });

        return response()->json(status: 204);
    }

    private function ownerProfile(Store $store): Store
    {
        $profile = $store->fresh()
            ->load(['businessHours', 'tags:id,store_id,name,slug'])
            ->makeVisible('business_info');

        $profile->setAttribute('hours', $profile->business_hours_text);
        $profile->setAttribute('business_info_text', data_get($profile->business_info, 'business_registration_number'));
        $profile->setAttribute('tag_names', $profile->tags->pluck('name')->values());

        return $profile;
    }

    private function authorizeOwner(Request $request, Store $store): void
    {
        $this->storeAccess->authorize($request->user(), $store, ['OWNER']);
    }

    private function primaryOwnerStore(Request $request): Store
    {
        return $this->storeAccess->primary($request->user());
    }

    private function normalizeProfilePayload(Request $request): void
    {
        $data = $request->all();

        foreach (['store_name' => 'name', 'store_description' => 'description', 'store_address' => 'address', 'store_phone' => 'phone'] as $alias => $field) {
            if (! array_key_exists($field, $data) && array_key_exists($alias, $data)) {
                $data[$field] = $data[$alias];
            }
            unset($data[$alias]);
        }

        $hours = $data['business_hours'] ?? $data['businessHours'] ?? $data['hours'] ?? null;
        if (array_key_exists('business_hours', $data) && $hours === null) {
            $data['business_hours_text'] = null;
            unset($data['business_hours']);
        } elseif (is_string($hours) || is_numeric($hours)) {
            $text = trim((string) $hours);
            $data['business_hours_text'] = $text;
            $dailyHours = $this->parseDailyBusinessHours($text);
            if ($dailyHours !== null) {
                $data['business_hours'] = $dailyHours;
            } else {
                unset($data['business_hours']);
            }
        }
        unset($data['businessHours'], $data['hours']);

        $businessInfo = $data['business_info'] ?? $data['businessInfo'] ?? $data['business_info_text'] ?? null;
        if (is_string($businessInfo) || is_numeric($businessInfo)) {
            $data['business_info'] = [
                'business_registration_number' => trim((string) $businessInfo),
            ];
        } elseif (is_array($businessInfo) && ! array_key_exists('business_info', $data)) {
            $data['business_info'] = $businessInfo;
        }
        unset($data['businessInfo'], $data['business_info_text']);

        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = collect($data['tags'])->map(function ($tag): array {
                if (is_string($tag) || is_numeric($tag)) {
                    $name = trim((string) $tag);

                    return ['name' => $name, 'slug' => $this->stableTagSlug($name)];
                }

                $name = trim((string) ($tag['name'] ?? $tag['label'] ?? $tag['value'] ?? ''));

                return [
                    'name' => $name,
                    'slug' => $tag['slug'] ?? $this->stableTagSlug($name),
                ];
            })->values()->all();
        }

        $request->replace($data);
    }

    private function stableTagSlug(string $name): string
    {
        $known = [
            '커피' => 'coffee',
            '음료' => 'beverage',
            '디저트' => 'dessert',
            '베이커리' => 'bakery',
            '브런치' => 'brunch',
            '로스터리' => 'roastery',
            '티(차)' => 'tea',
            '스터디카페' => 'study-cafe',
        ];

        return $known[$name] ?? (Str::slug($name) ?: 'tag-'.substr(sha1($name), 0, 12));
    }

    private function parseDailyBusinessHours(string $text): ?array
    {
        if (! preg_match('/^(?:매일\s*)?(\d{1,2})(?::(\d{2}))?\s*[-~]\s*(\d{1,2})(?::(\d{2}))?$/u', $text, $matches)) {
            return null;
        }

        $openingHour = (int) $matches[1];
        $openingMinute = (int) (($matches[2] ?? '') !== '' ? $matches[2] : 0);
        $closingHour = (int) $matches[3];
        $closingMinute = (int) (($matches[4] ?? '') !== '' ? $matches[4] : 0);
        if ($openingHour > 23 || $closingHour > 23 || $openingMinute > 59 || $closingMinute > 59) {
            return null;
        }

        $opening = sprintf('%02d:%02d', $openingHour, $openingMinute);
        $closing = sprintf('%02d:%02d', $closingHour, $closingMinute);
        if ($closing <= $opening) {
            return null;
        }

        return collect(range(0, 6))->map(fn (int $day): array => [
            'day_of_week' => $day,
            'opening_time' => $opening,
            'closing_time' => $closing,
            'is_closed' => false,
        ])->all();
    }

    private function summarizeBusinessHours(array $hours): ?string
    {
        $open = collect($hours)->filter(fn (array $row): bool => ! $row['is_closed']);
        if ($open->isEmpty()) {
            return '휴무';
        }

        $first = $open->first();
        $same = $open->every(fn (array $row): bool =>
            $row['opening_time'] === $first['opening_time'] && $row['closing_time'] === $first['closing_time']);
        if ($same && count($hours) === 7) {
            $format = fn (string $time): string => str_ends_with($time, ':00') ? substr($time, 0, 2) : $time;

            return $format($first['opening_time']).'-'.$format($first['closing_time']);
        }

        return $this->formatHoursSummary($hours);
    }

    private function formatHoursSummary(array $hours): string
    {
        $labels = ['일', '월', '화', '수', '목', '금', '토'];

        return collect($hours)->sortBy('day_of_week')->map(function (array $row) use ($labels): string {
            $label = $labels[$row['day_of_week']] ?? (string) $row['day_of_week'];

            return $row['is_closed'] ? $label.' 휴무' : $label.' '.$row['opening_time'].'-'.$row['closing_time'];
        })->join(', ');
    }
}
