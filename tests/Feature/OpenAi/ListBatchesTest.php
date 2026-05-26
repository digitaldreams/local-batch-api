<?php

namespace Tests\Feature\OpenAi;

use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListBatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_openai_batches(): void
    {
        Batch::factory()->count(2)->create(['provider_format' => 'openai']);
        Batch::factory()->create(['provider_format' => 'anthropic']);

        $response = $this->getJson('/api/openai/v1/batches');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_returns_empty_list_when_no_batches(): void
    {
        $this->getJson('/api/openai/v1/batches')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_limit_parameter_restricts_results(): void
    {
        Batch::factory()->count(10)->create(['provider_format' => 'openai']);

        $response = $this->getJson('/api/openai/v1/batches?limit=3')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_has_more_true_when_additional_results_exist(): void
    {
        Batch::factory()->count(5)->create(['provider_format' => 'openai']);

        $response = $this->getJson('/api/openai/v1/batches?limit=3')->assertOk();

        $this->assertTrue($response->json('has_more'));
    }

    public function test_has_more_false_when_all_results_fit(): void
    {
        Batch::factory()->count(2)->create(['provider_format' => 'openai']);

        $response = $this->getJson('/api/openai/v1/batches?limit=10')->assertOk();

        $this->assertFalse($response->json('has_more'));
    }

    public function test_first_id_and_last_id_populated(): void
    {
        Batch::factory()->count(3)->create(['provider_format' => 'openai']);

        $response = $this->getJson('/api/openai/v1/batches')->assertOk();

        $this->assertNotNull($response->json('first_id'));
        $this->assertNotNull($response->json('last_id'));
    }

    public function test_after_cursor_returns_older_batches(): void
    {
        $batches = Batch::factory()->count(3)->create(['provider_format' => 'openai']);
        $newest = $batches->sortByDesc('created_at')->first();

        $response = $this->getJson("/api/openai/v1/batches?after={$newest->id}")->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($newest->id, $ids->all());
    }
}
