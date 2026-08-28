<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentCorsTest extends TestCase
{
    public function test_vercel_frontend_origin_is_allowed_for_api_requests(): void
    {
        config(['cors.allowed_origins' => ['https://team-project-five-pink.vercel.app']]);

        $this->withHeaders([
            'Origin' => 'https://team-project-five-pink.vercel.app',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/stores')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://team-project-five-pink.vercel.app');
    }

    public function test_unknown_origin_does_not_receive_a_matching_allow_origin_header(): void
    {
        config(['cors.allowed_origins' => ['https://team-project-five-pink.vercel.app']]);

        $this->withHeaders([
            'Origin' => 'https://untrusted.example.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/stores')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://team-project-five-pink.vercel.app');
    }
}
