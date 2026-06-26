<?php

namespace Tests\Feature\OpenAi;

use BatchApi\Events\BatchCancelledEvent;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_pending_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Pending,
        ]);

        $this->postJson("/api/openai/v1/batches/{$batch->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('local_batch_api_batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Cancelled->value,
        ]);
    }

    public function test_returns_422_for_already_completed_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Completed,
        ]);

        $this->postJson("/api/openai/v1/batches/{$batch->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_processing_batch_becomes_cancelling(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Processing,
        ]);

        $this->postJson("/api/openai/v1/batches/{$batch->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelling');

        $this->assertDatabaseHas('local_batch_api_batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Cancelling->value,
        ]);
    }

    public function test_returns_422_for_already_cancelling_batch(): void
    {
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Cancelling,
        ]);

        $this->postJson("/api/openai/v1/batches/{$batch->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_fires_batch_cancelled_event(): void
    {
        Event::fake();
        $batch = Batch::factory()->create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Pending,
        ]);

        $this->postJson("/api/openai/v1/batches/{$batch->id}/cancel");

        Event::assertDispatched(BatchCancelledEvent::class, fn ($e) => $e->batch->id === $batch->id);
    }
}
