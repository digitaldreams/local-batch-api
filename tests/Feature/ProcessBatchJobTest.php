<?php

namespace Tests\Feature;

use BatchApi\Events\BatchCompletedEvent;
use BatchApi\Events\BatchFailedEvent;
use BatchApi\Events\BatchItemCompletedEvent;
use BatchApi\Events\BatchItemStartedEvent;
use BatchApi\Events\BatchProcessingEvent;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use BatchApi\Shared\Batch\ProcessBatchJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessBatchJobTest extends TestCase
{
    use RefreshDatabase;

    private function ollamaSuccess(string $content = 'Hello there'): array
    {
        return [
            'model' => 'llama3.2',
            'message' => ['role' => 'assistant', 'content' => $content],
            'done_reason' => 'stop',
            'done' => true,
            'prompt_eval_count' => 10,
            'eval_count' => 5,
        ];
    }

    private function pendingBatch(int $count = 1, string $format = 'anthropic'): Batch
    {
        $payload = array_map(fn ($i) => [
            'custom_id' => "req-{$i}",
            'model' => 'llama3.2',
            'max_tokens' => 512,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], range(1, $count));

        return Batch::create([
            'provider_format' => $format,
            'status' => BatchStatus::Pending,
            'payload' => $payload,
            'request_count' => $count,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_marks_batch_completed_after_processing(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess())]);

        $batch = $this->pendingBatch();
        (new ProcessBatchJob($batch->id))->handle();

        $this->assertDatabaseHas('local_batch_api_batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Completed->value,
            'succeeded_count' => 1,
            'errored_count' => 0,
        ]);
        $this->assertNotNull($batch->fresh()->completed_at);
    }

    public function test_fires_batch_processing_event(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess())]);

        $batch = $this->pendingBatch();
        (new ProcessBatchJob($batch->id))->handle();

        Event::assertDispatched(BatchProcessingEvent::class, fn ($e) => $e->batch->id === $batch->id);
    }

    public function test_fires_item_started_and_completed_events_per_request(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess())]);

        $batch = $this->pendingBatch(count: 3);
        (new ProcessBatchJob($batch->id))->handle();

        Event::assertDispatchedTimes(BatchItemStartedEvent::class, 3);
        Event::assertDispatchedTimes(BatchItemCompletedEvent::class, 3);
    }

    public function test_fires_batch_completed_event_with_results(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess('Paris'))]);

        $batch = $this->pendingBatch();
        (new ProcessBatchJob($batch->id))->handle();

        Event::assertDispatched(BatchCompletedEvent::class, function ($e) use ($batch) {
            return $e->batch->id === $batch->id
                && count($e->results) === 1
                && $e->results[0]->succeeded === true
                && $e->results[0]->content === 'Paris';
        });
    }

    public function test_stores_results_in_raw_response(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess('Stored result'))]);

        $batch = $this->pendingBatch();
        (new ProcessBatchJob($batch->id))->handle();

        $raw = $batch->fresh()->raw_response;
        $this->assertNotNull($raw);
        $this->assertCount(1, $raw);
        $this->assertSame('req-1', $raw[0]['custom_id']);
        $this->assertTrue($raw[0]['succeeded']);
        $this->assertSame('Stored result', $raw[0]['content']);
    }

    public function test_records_errored_item_when_ollama_returns_500(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response([], 500)]);

        $batch = $this->pendingBatch();
        (new ProcessBatchJob($batch->id))->handle();

        $this->assertDatabaseHas('local_batch_api_batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Completed->value,
            'succeeded_count' => 0,
            'errored_count' => 1,
        ]);
    }

    public function test_skips_processing_when_batch_not_pending(): void
    {
        Event::fake();
        Http::fake();

        $batch = Batch::create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Completed,
            'payload' => [],
            'request_count' => 0,
            'expires_at' => now()->addHours(24),
        ]);

        (new ProcessBatchJob($batch->id))->handle();

        $this->assertDatabaseHas('local_batch_api_batches', ['id' => $batch->id, 'status' => BatchStatus::Completed->value]);
        Event::assertNotDispatched(BatchProcessingEvent::class);
        Http::assertNothingSent();
    }

    public function test_resumes_batch_stuck_in_processing_on_retry(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess())]);

        $batch = $this->pendingBatch();
        $batch->update(['status' => BatchStatus::Processing, 'in_progress_at' => now()]);

        (new ProcessBatchJob($batch->id))->handle();

        $this->assertDatabaseHas('local_batch_api_batches', [
            'id' => $batch->id,
            'status' => BatchStatus::Completed->value,
            'succeeded_count' => 1,
            'errored_count' => 0,
        ]);
    }

    public function test_cancelling_batch_is_marked_cancelled_without_hitting_ollama(): void
    {
        Event::fake();
        Http::fake();

        $batch = Batch::create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Cancelling,
            'payload' => [['custom_id' => 'req-1', 'model' => 'llama3.2', 'max_tokens' => 512, 'messages' => []]],
            'request_count' => 1,
            'expires_at' => now()->addHours(24),
        ]);

        (new ProcessBatchJob($batch->id))->handle();

        $this->assertDatabaseHas('local_batch_api_batches', ['id' => $batch->id, 'status' => BatchStatus::Cancelled->value]);
        Event::assertNotDispatched(BatchProcessingEvent::class);
        Http::assertNothingSent();
    }

    public function test_failed_marks_batch_failed_and_fires_event(): void
    {
        Event::fake();

        $batch = $this->pendingBatch();

        (new ProcessBatchJob($batch->id))->failed(new \RuntimeException('Queue exploded'));

        $this->assertDatabaseHas('local_batch_api_batches', ['id' => $batch->id, 'status' => BatchStatus::Failed->value]);
        Event::assertDispatched(BatchFailedEvent::class, fn ($e) => $e->batch->id === $batch->id);
    }

    public function test_creates_output_file_for_openai_format_batch(): void
    {
        Event::fake();
        Http::fake(['*' => Http::response($this->ollamaSuccess())]);

        $batch = Batch::create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Pending,
            'payload' => [['custom_id' => 'req-1', 'model' => 'llama3.2', 'max_tokens' => 512, 'messages' => [['role' => 'user', 'content' => 'Hello']]]],
            'input_file_id' => 'file-test123',
            'request_count' => 1,
            'expires_at' => now()->addHours(24),
        ]);

        (new ProcessBatchJob($batch->id))->handle();

        $batch->refresh();
        $this->assertNotNull($batch->output_file_id);
        $this->assertStringStartsWith('file-', $batch->output_file_id);
        $this->assertDatabaseHas('local_batch_api_batch_files', ['id' => $batch->output_file_id, 'purpose' => 'batch_output']);
    }

    public function test_gracefully_handles_missing_batch(): void
    {
        Event::fake();

        (new ProcessBatchJob('non-existent-uuid'))->handle();

        Event::assertNotDispatched(BatchProcessingEvent::class);
    }
}
