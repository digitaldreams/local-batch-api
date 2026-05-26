<?php

namespace Tests\Feature\Anthropic;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_batch_with_in_progress_status(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Pending,
        ]);

        $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('processing_status', 'in_progress')
            ->assertJsonPath('type', 'message_batch');
    }

    public function test_returns_batch_with_ended_status_when_completed(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Completed,
        ]);

        $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('processing_status', 'ended');
    }

    public function test_returns_404_for_unknown_batch(): void
    {
        $this->getJson('/api/anthropic/v1/messages/batches/nonexistent-id')
            ->assertNotFound();
    }

    public function test_returns_canceling_status_for_cancelling_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Cancelling,
        ]);

        $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('processing_status', 'canceling');
    }

    public function test_returns_ended_status_for_failed_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Failed,
        ]);

        $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('processing_status', 'ended');
    }

    public function test_results_url_populated_when_ended(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Completed,
        ]);

        $response = $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}")->assertOk();

        $this->assertStringContainsString($batch->id, $response->json('results_url'));
    }

    public function test_results_url_null_when_not_ended(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Processing,
        ]);

        $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('results_url', null);
    }
}
