<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.3.0',
    title: 'CafeOn API',
    description: 'CafeOn 손님·사장님·SUPER_ADMIN 백엔드 API 문서. 사장님 운영 API는 Bearer 토큰으로 로그인 계정의 OWNER 매장을 자동 선택합니다.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token'
)]
final class OpenApiSpec {}
