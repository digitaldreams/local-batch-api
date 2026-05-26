<?php

namespace Tests\Feature\OpenAi;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_batch_with_validating_status(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Pending,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'validating')
            ->assertJsonPath('object', 'batch');
    }

    public function test_returns_in_progress_status_for_processing_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Processing,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_returns_batch_with_completed_status(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Completed,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_returns_cancelling_status_for_cancelling_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Cancelling,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'cancelling');
    }

    public function test_returns_cancelled_status_for_cancelled_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Cancelled,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_returns_failed_status_for_failed_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Failed,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'failed');
    }

    public function test_returns_expired_status_for_expired_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Expired,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'expired');
    }

    public function test_output_file_id_populated_when_completed(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Completed,
            'output_file_id' => 'file-output-123',
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('output_file_id', 'file-output-123');
    }

    public function test_output_file_id_null_when_not_completed(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Processing,
            'output_file_id' => null,
        ]);

        $this->getJson("/api/openai/v1/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('output_file_id', null);
    }

    public function test_returns_404_for_unknown_batch(): void
    {
        $this->getJson('/api/openai/v1/batches/nonexistent-id')
            ->assertNotFound();
    }
}
