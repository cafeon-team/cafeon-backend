<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerRouteTest extends TestCase
{
    public function test_swagger_short_url_redirects_to_documentation(): void
    {
        $this->get('/swagger')
            ->assertRedirect('/api/documentation');
    }

    public function test_swagger_documentation_is_accessible(): void
    {
        $this->get('/api/documentation')
            ->assertOk();
    }

    public function test_owner_operation_contracts_are_in_generated_swagger(): void
    {
        $this->artisan('swagger:generate-routes')->assertSuccessful();
        $document = json_decode(
            (string) file_get_contents(storage_path('api-docs/api-docs.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('1.3.0', $document['info']['version']);
        $this->assertSame(rtrim((string) config('app.url'), '/'), $document['servers'][0]['url']);
        $this->assertSame('사장님 통합 대시보드 조회', $document['paths']['/api/owner/dashboard']['get']['summary']);
        $this->assertSame([['sanctum' => []]], $document['paths']['/api/owner/dashboard']['get']['security']);

        foreach ([
            ['/api/owner/store/business-status', 'patch'],
            ['/api/owner/reservations/{reservation}/status', 'patch'],
            ['/api/owner/menus', 'post'],
            ['/api/owner/menu-categories', 'post'],
            ['/api/owner/seats', 'post'],
            ['/api/owner/seats/reset', 'post'],
            ['/api/owner/stores/{store}/seats/reset', 'post'],
            ['/api/owner/seats/{seat}', 'patch'],
            ['/api/owner/seats/availability', 'patch'],
        ] as [$path, $method]) {
            $this->assertArrayHasKey('requestBody', $document['paths'][$path][$method], "{$method} {$path} requestBody 누락");
        }

        $loginProperties = $document['paths']['/api/auth/owner/login']['post']['responses']['200']['content']['application/json']['schema']['properties'];
        $this->assertArrayHasKey('store_id', $loginProperties);
        $this->assertArrayHasKey('store', $loginProperties);
        $this->assertArrayHasKey('memberships', $loginProperties);
        $this->assertFileEquals(
            storage_path('api-docs/api-docs.json'),
            storage_path('api-docs/cafeon-team-api.json'),
        );
    }
}
