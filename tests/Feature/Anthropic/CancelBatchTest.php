<?php

namespace Tests\Feature\Anthropic;

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
        $batch = Batch::factory()->create(['status' => BatchStatus::Pending]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel")
            ->assertOk()
            ->assertJsonPath('processing_status', 'ended');

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Cancelled->value,
        ]);
    }

    public function test_returns_422_for_already_completed_batch(): void
    {
        $batch = Batch::factory()->create(['status' => BatchStatus::Completed]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_returns_422_for_already_cancelled_batch(): void
    {
        $batch = Batch::factory()->create(['status' => BatchStatus::Cancelled]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_processing_batch_becomes_cancelling_with_canceling_status(): void
    {
        $batch = Batch::factory()->create(['status' => BatchStatus::Processing]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel")
            ->assertOk()
            ->assertJsonPath('processing_status', 'canceling');

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Cancelling->value,
        ]);
    }

    public function test_returns_422_for_already_cancelling_batch(): void
    {
        $batch = Batch::factory()->create(['status' => BatchStatus::Cancelling]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_fires_batch_cancelled_event(): void
    {
        Event::fake();
        $batch = Batch::factory()->create(['status' => BatchStatus::Pending]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel");

        Event::assertDispatched(BatchCancelledEvent::class, fn ($e) => $e->batch->id === $batch->id);
    }

    public function test_sets_cancel_initiated_at_timestamp(): void
    {
        $batch = Batch::factory()->create(['status' => BatchStatus::Pending]);

        $this->postJson("/api/anthropic/v1/messages/batches/{$batch->id}/cancel")->assertOk();

        $this->assertNotNull($batch->fresh()->cancel_initiated_at);
    }
}
