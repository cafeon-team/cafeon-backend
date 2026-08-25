<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerBirthDateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_and_read_birth_date_from_server(): void
    {
        $user = User::factory()->create(['birth_date' => null]);
        Sanctum::actingAs($user);

        $this->putJson('/api/users/me', ['birth_date' => '1995-03-21'])
            ->assertOk()->assertJsonPath('user.birth_date', '1995-03-21');

        $this->getJson('/api/users/me')
            ->assertOk()->assertJsonPath('user.birth_date', '1995-03-21');
    }

    public function test_future_birth_date_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/users/me', ['birth_date' => now()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    }
}
