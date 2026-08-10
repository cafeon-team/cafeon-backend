<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_can_be_filtered_by_existing_tag(): void
    {
        $quiet = Store::create(['name' => 'Quiet', 'slug' => 'quiet', 'address' => 'Seoul', 'is_active' => true]);
        Store::create(['name' => 'Busy', 'slug' => 'busy', 'address' => 'Seoul', 'is_active' => true]);
        Tag::create(['store_id' => $quiet->id, 'name' => '조용한', 'slug' => 'quiet']);

        $this->getJson('/api/stores?tag=quiet')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.slug', 'quiet');
    }
}
