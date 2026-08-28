<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router;

class GenerateRouteSwagger extends Command
{
    protected $signature = 'swagger:generate-routes';

    protected $description = 'Generate L5 Swagger documentation and include every registered API route';

    public function handle(Router $router): int
    {
        $this->call('l5-swagger:generate');
        $path = storage_path('api-docs/api-docs.json');
        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $document['servers'] = [[
            'url' => rtrim((string) config('app.url'), '/'),
            'description' => '현재 Laravel APP_URL 서버',
        ]];

        foreach ($router->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), 'api/documentation')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $swaggerPath = '/'.preg_replace('/\{([^}]+)\??\}/', '{$1}', $route->uri());
                $operation = strtolower($method);
                if (isset($document['paths'][$swaggerPath][$operation])) {
                    continue;
                }

                $document['paths'][$swaggerPath][$operation] = $this->operation($route, $method);
            }
        }

        $this->applyContractOverrides($document);

        ksort($document['paths']);
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
        file_put_contents($path, $json);
        file_put_contents(storage_path('api-docs/cafeon-team-api.json'), $json);
        $this->info(count($document['paths']).' API path(s) written to Swagger.');

        return self::SUCCESS;
    }

    private function applyContractOverrides(array &$document): void
    {
        $document['paths']['/api/map/kakao-cafes']['get']['parameters'] = [
            ['name' => 'sw_lat', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'number', 'format' => 'double']],
            ['name' => 'sw_lng', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'number', 'format' => 'double']],
            ['name' => 'ne_lat', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'number', 'format' => 'double']],
            ['name' => 'ne_lng', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'number', 'format' => 'double']],
            ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 45, 'default' => 1]],
            ['name' => 'size', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 15, 'default' => 15]],
        ];

        $document['paths']['/api/users/me']['put']['requestBody'] = $this->jsonBody([
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string', 'format' => 'email'],
            'phone' => ['type' => 'string', 'nullable' => true],
            'profile_image_url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
            'birth_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
        ]);

        foreach (['put', 'patch'] as $method) {
            $document['paths']['/api/owner/profile'][$method]['requestBody'] = $this->jsonBody([
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'phone' => ['type' => 'string', 'nullable' => true],
                'profile_image_url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                'birth_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
            ]);
        }

        $document['paths']['/api/owner/stores/{store}']['patch']['requestBody'] = $this->jsonBody([
            'name' => ['type' => 'string'],
            'description' => ['type' => 'string', 'nullable' => true],
            'address' => ['type' => 'string', 'nullable' => true],
            'detail_address' => ['type' => 'string', 'nullable' => true],
            'phone' => ['type' => 'string', 'nullable' => true],
            'business_hours_text' => ['type' => 'string', 'nullable' => true, 'example' => '09:00-22:00'],
            'business_info_text' => ['type' => 'string', 'nullable' => true, 'example' => '123-45-67890'],
            'thumbnail_url' => ['type' => 'string', 'nullable' => true],
            'reservation_enabled' => ['type' => 'boolean'],
            'business_info' => [
                'type' => 'object',
                'nullable' => true,
                'properties' => [
                    'business_registration_number' => ['type' => 'string', 'nullable' => true],
                    'representative_name' => ['type' => 'string', 'nullable' => true],
                    'company_name' => ['type' => 'string', 'nullable' => true],
                    'business_type' => ['type' => 'string', 'nullable' => true],
                    'business_item' => ['type' => 'string', 'nullable' => true],
                    'business_address' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'business_hours' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['day_of_week', 'is_closed'],
                    'properties' => [
                        'day_of_week' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 6],
                        'opening_time' => ['type' => 'string', 'nullable' => true, 'example' => '09:00'],
                        'closing_time' => ['type' => 'string', 'nullable' => true, 'example' => '22:00'],
                        'is_closed' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'tags' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string', 'example' => '와이파이'],
                        'slug' => ['type' => 'string', 'nullable' => true, 'example' => 'wifi'],
                    ],
                ],
            ],
        ]);

        $document['paths']['/api/owner/store']['patch']['requestBody'] =
            $document['paths']['/api/owner/stores/{store}']['patch']['requestBody'];

        $this->applyOwnerOperationContracts($document);
        $this->applyReviewImageContracts($document);
    }

    private function applyReviewImageContracts(array &$document): void
    {
        $image = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'review_id' => ['type' => 'integer'],
                'image_url' => ['type' => 'string', 'format' => 'uri'],
                'alt_text' => ['type' => 'string', 'nullable' => true],
                'sort_order' => ['type' => 'integer'],
            ],
        ];
        $review = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'store_id' => ['type' => 'integer'],
                'user_id' => ['type' => 'integer'],
                'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'content' => ['type' => 'string'],
                'images' => ['type' => 'array', 'items' => $image],
            ],
        ];
        $imageUrls = [
            'type' => 'array', 'maxItems' => 5, 'uniqueItems' => true,
            'description' => '먼저 /api/uploads/images에 본인 토큰으로 업로드한 CafeOn 이미지 URL만 허용',
            'items' => ['type' => 'string', 'format' => 'uri'],
        ];

        $document['paths']['/api/stores/{store}/reviews']['post']['tags'] = ['리뷰'];
        $document['paths']['/api/stores/{store}/reviews']['post']['summary'] = '리뷰 및 리뷰 사진 등록';
        $document['paths']['/api/stores/{store}/reviews']['post']['requestBody'] = $this->jsonBody([
            'customer_visit_id' => ['type' => 'integer', 'nullable' => true],
            'order_id' => ['type' => 'integer', 'nullable' => true],
            'reservation_id' => ['type' => 'integer', 'nullable' => true],
            'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            'content' => ['type' => 'string', 'maxLength' => 3000],
            'image_urls' => $imageUrls,
        ], ['rating', 'content']);
        $document['paths']['/api/stores/{store}/reviews']['post']['responses']['201'] = $this->jsonResponse([
            'message' => ['type' => 'string'], 'review' => $review,
        ], '리뷰 및 이미지 저장 성공');

        $document['paths']['/api/stores/{store}/reviews']['get']['tags'] = ['리뷰'];
        $document['paths']['/api/stores/{store}/reviews']['get']['summary'] = '카페 리뷰·사진 목록 조회';
        $document['paths']['/api/stores/{store}/reviews']['get']['responses']['200'] = $this->jsonResponse([
            'data' => ['type' => 'array', 'items' => $review],
            'current_page' => ['type' => 'integer'],
            'last_page' => ['type' => 'integer'],
            'per_page' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
        ]);

        $document['paths']['/api/reviews/{review}']['put']['tags'] = ['리뷰'];
        $document['paths']['/api/reviews/{review}']['put']['summary'] = '내 리뷰·사진 수정';
        $document['paths']['/api/reviews/{review}']['put']['requestBody'] = $this->jsonBody([
            'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            'content' => ['type' => 'string', 'maxLength' => 3000],
            'image_urls' => $imageUrls,
        ]);
        $document['paths']['/api/reviews/{review}']['put']['responses']['200'] = $this->jsonResponse([
            'message' => ['type' => 'string'], 'review' => $review,
        ]);
        $document['paths']['/api/reviews/{review}']['delete']['tags'] = ['리뷰'];
        $document['paths']['/api/reviews/{review}']['delete']['summary'] = '내 리뷰 및 실제 이미지 파일 삭제';

        $document['paths']['/api/uploads/images']['post']['tags'] = ['이미지'];
        $document['paths']['/api/uploads/images']['post']['summary'] = '인증 사용자 이미지 업로드';
        $document['paths']['/api/uploads/images']['post']['description'] = '업로드 소유자를 기록합니다. 반환 URL은 같은 사용자의 리뷰 image_urls에 사용할 수 있습니다.';
        $document['paths']['/api/uploads/images']['post']['requestBody'] = [
            'required' => true,
            'content' => ['multipart/form-data' => ['schema' => [
                'type' => 'object', 'required' => ['image'], 'properties' => [
                    'image' => ['type' => 'string', 'format' => 'binary', 'description' => 'jpg/jpeg/png/webp/gif, 최대 5MB'],
                ],
            ]]],
        ];
        $document['paths']['/api/uploads/images']['post']['responses']['201'] = $this->jsonResponse([
            'path' => ['type' => 'string', 'example' => 'blog/example.jpg'],
            'url' => ['type' => 'string', 'format' => 'uri'],
            'image_url' => ['type' => 'string', 'format' => 'uri'],
        ], '업로드 및 소유권 기록 성공');
        $document['paths']['/api/uploads/images']['post']['responses']['503'] = $this->jsonResponse([
            'message' => ['type' => 'string'],
            'error_code' => ['type' => 'string', 'example' => 'IMAGE_STORAGE_UNAVAILABLE'],
        ], '이미지 저장소 또는 업로드 메타데이터 DB 사용 불가');
    }

    private function applyOwnerOperationContracts(array &$document): void
    {
        $operations = [
            ['/api/owner/dashboard', 'get', '사장님 통합 대시보드 조회', '영업 상태, 매출, 좌석, 예약, 메뉴를 로그인 계정의 대표 매장 기준으로 조회합니다.'],
            ['/api/owner/store', 'get', '내 대표 매장 조회', '매장 ID 없이 로그인한 사장님의 대표 OWNER 매장을 조회합니다.'],
            ['/api/owner/store', 'patch', '내 대표 매장 프로필 저장', '매장명, 설명, 주소, 전화번호, 영업시간, 사업자정보와 태그를 저장합니다.'],
            ['/api/owner/stores', 'get', '내 소유 매장 목록 조회', '로그인 계정에 활성 OWNER로 연결된 매장 목록과 대표 store_id를 반환합니다.'],
            ['/api/owner/store/business-status', 'patch', '매장 운영 상태 저장', '운영중/운영종료 상태를 stores.is_open에 저장합니다.'],
            ['/api/owner/reservations', 'get', '내 매장 예약 목록 조회', '전체 예약 또는 수락 대기 등 상태별 예약을 조회합니다.'],
            ['/api/owner/reservations/{reservation}/status', 'patch', '예약 수락·거절·상태 변경', '본인 매장 예약의 상태를 변경하고 승인자와 처리 시각을 저장합니다.'],
            ['/api/owner/menus', 'get', '내 매장 메뉴·카테고리 조회', '재로그인 후 저장된 메뉴와 카테고리를 복원합니다.'],
            ['/api/owner/menus', 'post', '내 매장 메뉴 등록', '대표 OWNER 매장에 메뉴를 등록합니다.'],
            ['/api/owner/menu-categories', 'post', '내 매장 메뉴 카테고리 등록', '커피, 음료, 디저트 등의 메뉴 카테고리를 등록합니다.'],
            ['/api/owner/seats', 'get', '내 매장 좌석 현황 조회', '저장된 좌석과 현재 상태를 조회합니다.'],
            ['/api/owner/seats', 'post', '내 매장 좌석 등록', '대표 OWNER 매장에 좌석을 등록합니다.'],
            ['/api/owner/seats/reset', 'post', '내 매장 좌석 설정 초기화', '입력한 좌석 수만큼 1번부터 다시 구성하고 모두 비어있음 상태로 초기화합니다.'],
            ['/api/owner/seats/{seat}', 'patch', '좌석 상태 변경', '비어있음, 사용중, 대기/점검 상태를 저장합니다.'],
            ['/api/owner/seats/{seat}', 'delete', '좌석 삭제', '본인 매장의 좌석을 삭제합니다.'],
            ['/api/owner/seats/availability', 'patch', '좌석 상태 일괄 변경', '여러 좌석의 상태를 한 번에 저장합니다.'],
        ];

        foreach ($operations as [$path, $method, $summary, $description]) {
            if (! isset($document['paths'][$path][$method])) {
                continue;
            }
            $document['paths'][$path][$method]['tags'] = ['사장님 운영'];
            $document['paths'][$path][$method]['summary'] = $summary;
            $document['paths'][$path][$method]['description'] = $description;
        }

        $document['paths']['/api/auth/owner/login']['post']['tags'] = ['사장님 인증'];
        $document['paths']['/api/auth/owner/login']['post']['summary'] = '사장님 로그인 및 매장정보 복원';
        $document['paths']['/api/auth/owner/login']['post']['description'] = 'ADMIN 계정을 인증하고 token, store_id, store, membership, stores, memberships를 반환합니다.';
        $document['paths']['/api/auth/owner/login']['post']['requestBody'] = $this->jsonBody([
            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'owner@cafeon.test'],
            'password' => ['type' => 'string', 'format' => 'password', 'example' => 'password1234'],
        ], ['email', 'password']);
        $document['paths']['/api/auth/owner/login']['post']['responses']['200'] = $this->jsonResponse([
            'token' => ['type' => 'string'],
            'token_type' => ['type' => 'string', 'example' => 'Bearer'],
            'user' => ['type' => 'object'],
            'store_id' => ['type' => 'integer', 'nullable' => true, 'example' => 1],
            'store' => ['type' => 'object', 'nullable' => true],
            'membership' => ['type' => 'object', 'nullable' => true],
            'stores' => ['type' => 'array', 'items' => ['type' => 'object']],
            'memberships' => ['type' => 'array', 'items' => ['type' => 'object']],
        ], '로그인 성공 및 사장님 매장 컨텍스트 반환');

        $document['paths']['/api/auth/social/exchange']['post']['tags'] = ['사장님 인증'];
        $document['paths']['/api/auth/social/exchange']['post']['summary'] = '소셜 로그인 코드 교환';
        $document['paths']['/api/auth/social/exchange']['post']['description'] = 'owner 소셜 로그인인 경우 일반 사장님 로그인과 동일하게 store_id와 매장 컨텍스트를 반환합니다.';
        $document['paths']['/api/auth/social/exchange']['post']['requestBody'] = $this->jsonBody([
            'code' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
            'role' => ['type' => 'string', 'enum' => ['customer', 'owner'], 'example' => 'owner'],
        ], ['code']);

        $document['paths']['/api/owner/store/business-status']['patch']['requestBody'] = $this->jsonBody([
            'is_open' => ['type' => 'boolean', 'example' => true, 'description' => 'true=운영중, false=운영종료'],
            'is_active' => ['type' => 'boolean', 'example' => true, 'description' => '기존 프론트 호환 필드'],
            'status' => ['type' => 'string', 'enum' => ['OPEN', 'CLOSED', '운영중', '운영종료'], 'description' => '문자열 호환 필드'],
        ]);
        $document['paths']['/api/owner/store/business-status']['patch']['responses']['200'] = $this->jsonResponse([
            'message' => ['type' => 'string', 'example' => '영업 중으로 변경되었습니다.'],
            'store' => ['type' => 'object'],
        ]);

        $document['paths']['/api/owner/reservations']['get']['parameters'] = [
            ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['PENDING_APPROVAL', 'AWAITING_PAYMENT', 'CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED', 'NO_SHOW', 'PAYMENT_FAILED', 'EXPIRED']]],
            ['name' => 'date', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
        ];
        $document['paths']['/api/owner/reservations/{reservation}/status']['patch']['requestBody'] = $this->jsonBody([
            'status' => ['type' => 'string', 'enum' => ['CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED', 'NO_SHOW', 'PAYMENT_FAILED', 'EXPIRED', 'ACCEPTED', '수락', '거절']],
            'reason' => ['type' => 'string', 'nullable' => true, 'maxLength' => 500, 'description' => '거절 시 필수'],
        ], ['status']);

        $document['paths']['/api/owner/menus']['get']['parameters'] = [
            ['name' => 'category_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
            ['name' => 'is_available', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
            ['name' => 'keyword', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
        ];
        $document['paths']['/api/owner/menus']['post']['requestBody'] = $this->jsonBody([
            'category_id' => ['type' => 'integer', 'nullable' => true],
            'category' => ['type' => 'string', 'nullable' => true, 'example' => '커피', 'description' => '카테고리명으로 자동 연결 가능'],
            'name' => ['type' => 'string', 'example' => '아메리카노'],
            'description' => ['type' => 'string', 'nullable' => true],
            'price' => ['type' => 'number', 'example' => 4500],
            'image_url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
            'is_available' => ['type' => 'boolean', 'default' => true],
            'soldOut' => ['type' => 'boolean', 'description' => '기존 프론트 호환 필드'],
        ], ['name', 'price']);
        $document['paths']['/api/owner/menu-categories']['post']['requestBody'] = $this->jsonBody([
            'name' => ['type' => 'string', 'example' => '커피'],
            'sort_order' => ['type' => 'integer', 'minimum' => 0],
            'is_active' => ['type' => 'boolean', 'default' => true],
        ], ['name']);

        $seatStatus = ['type' => 'string', 'enum' => ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE', '비어있음', '사용중', '대기', 'EMPTY', 'OCCUPIED', 'WAITING']];
        $document['paths']['/api/owner/seats']['post']['requestBody'] = $this->jsonBody([
            'seat_code' => ['type' => 'string', 'example' => 'A1'],
            'seat_name' => ['type' => 'string', 'example' => '창가 좌석'],
            'seat_type' => ['type' => 'string', 'enum' => ['WINDOW', 'NORMAL', 'GROUP', 'OUTDOOR']],
            'capacity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'example' => 2],
            'floor_number' => ['type' => 'integer', 'default' => 1],
            'status' => $seatStatus,
            'is_active' => ['type' => 'boolean', 'default' => true],
        ], ['seat_code', 'seat_name', 'capacity']);
        $resetBody = $this->jsonBody([
            'total_seats' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'example' => 15],
            'seat_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'total_seats 호환 별칭'],
            'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'total_seats 호환 별칭'],
        ]);
        $resetBody['content']['application/json']['schema']['anyOf'] = [
            ['required' => ['total_seats']],
            ['required' => ['seat_count']],
            ['required' => ['count']],
        ];
        $resetResponse = $this->jsonResponse([
            'message' => ['type' => 'string', 'example' => '좌석 설정이 초기화되었습니다.'],
            'seats' => ['type' => 'array', 'items' => ['type' => 'object']],
            'availability' => ['type' => 'object'],
        ], '좌석 초기화 및 DB 저장 성공');
        foreach (['/api/owner/seats/reset', '/api/owner/stores/{store}/seats/reset'] as $resetPath) {
            $document['paths'][$resetPath]['post']['tags'] = ['사장님 운영'];
            $document['paths'][$resetPath]['post']['summary'] = '좌석 설정 초기화';
            $document['paths'][$resetPath]['post']['description'] = '좌석을 1번부터 요청 수만큼 재구성해 DB에 저장합니다. 로그아웃 후 재로그인해도 GET 좌석 API로 복원됩니다.';
            $document['paths'][$resetPath]['post']['requestBody'] = $resetBody;
            $document['paths'][$resetPath]['post']['responses']['200'] = $resetResponse;
        }
        $document['paths']['/api/owner/seats/{seat}']['patch']['requestBody'] = $this->jsonBody([
            'status' => $seatStatus,
        ], ['status']);
        $document['paths']['/api/owner/seats/availability']['patch']['requestBody'] = $this->jsonBody([
            'seats' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 100,
                'items' => [
                    'type' => 'object',
                    'required' => ['id', 'status'],
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'status' => $seatStatus,
                    ],
                ],
            ],
        ], ['seats']);

        $document['paths']['/api/owner/dashboard']['get']['responses']['200'] = $this->jsonResponse([
            'store' => ['type' => 'object'],
            'summary' => [
                'type' => 'object',
                'properties' => [
                    'is_open' => ['type' => 'boolean'],
                    'today_sales' => ['type' => 'integer'],
                    'seat_count' => ['type' => 'integer'],
                    'seat_capacity' => ['type' => 'integer'],
                    'reservation_count' => ['type' => 'integer'],
                    'pending_reservation_count' => ['type' => 'integer'],
                ],
            ],
            'menuItems' => ['type' => 'array', 'items' => ['type' => 'object']],
            'reservations' => ['type' => 'array', 'items' => ['type' => 'object']],
            'pendingReservations' => ['type' => 'array', 'items' => ['type' => 'object']],
        ], '사장님 운영화면 통합 데이터');
    }

    private function jsonBody(array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    private function jsonResponse(array $properties, string $description = '요청 성공'): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'object', 'properties' => $properties],
                ],
            ],
        ];
    }

    private function operation(LaravelRoute $route, string $method): array
    {
        preg_match_all('/\{([^}]+)\??\}/', $route->uri(), $matches);
        $action = class_basename((string) $route->getActionName());
        $tag = ucfirst(explode('/', str_replace('api/', '', $route->uri()))[0] ?: 'API');
        $operation = [
            'tags' => [$tag],
            'summary' => str_replace('@', ' ', $action),
            'operationId' => strtolower($method).'_'.preg_replace('/[^a-zA-Z0-9]+/', '_', $route->uri()),
            'responses' => [
                '200' => ['description' => 'Successful response'],
                '422' => ['description' => 'Validation failed'],
            ],
        ];

        foreach ($matches[1] as $parameter) {
            $operation['parameters'][] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
            ];
        }

        if (collect($route->gatherMiddleware())->contains(fn ($middleware) => str_contains((string) $middleware, 'sanctum'))) {
            $operation['security'] = [['sanctum' => []]];
            $operation['responses']['401'] = ['description' => 'Unauthenticated'];
            $operation['responses']['403'] = ['description' => 'Forbidden'];
        }

        return $operation;
    }
}
