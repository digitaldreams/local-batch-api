<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSecretTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inference.api_secret', 'top-secret');
    }

    public function test_rejects_request_without_secret(): void
    {
        $this->getJson('/api/anthropic/v1/messages/batches')->assertStatus(401);
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $this->getJson('/api/anthropic/v1/messages/batches', ['X-Batch-Api-Key' => 'nope'])
            ->assertStatus(401);
    }

    public function test_allows_request_with_correct_secret_header(): void
    {
        $this->getJson('/api/anthropic/v1/messages/batches', ['X-Batch-Api-Key' => 'top-secret'])
            ->assertOk();
    }

    public function test_allows_request_with_bearer_token(): void
    {
        $this->getJson('/api/anthropic/v1/messages/batches', ['Authorization' => 'Bearer top-secret'])
            ->assertOk();
    }
}
