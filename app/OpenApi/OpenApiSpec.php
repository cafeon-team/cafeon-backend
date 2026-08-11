<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'CafeOn API',
    description: 'CafeOn backend API documentation'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8001',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token'
)]
final class OpenApiSpec
{
    #[OA\Get(
        path: '/api/stores',
        summary: 'Store list',
        tags: ['Stores'],
        responses: [
            new OA\Response(response: 200, description: 'Successful response'),
        ]
    )]
    public function stores(): void
    {
    }
}
