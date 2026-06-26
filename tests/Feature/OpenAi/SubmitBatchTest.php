<?php

namespace Tests\Feature\OpenAi;

use BatchApi\Events\BatchCreatedEvent;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\BatchFile;
use BatchApi\Shared\Batch\ProcessBatchJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmitBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_201_with_batch_object(): void
    {
        Queue::fake();
        $file = BatchFile::factory()->create();

        $response = $this->postJson('/api/openai/v1/batches', [
            'input_file_id' => $file->id,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'object', 'status', 'input_file_id', 'request_counts'])
            ->assertJsonPath('object', 'batch')
            ->assertJsonPath('status', 'validating')
            ->assertJsonPath('input_file_id', $file->id);
    }

    public function test_stores_batch_in_database(): void
    {
        Queue::fake();
        $file = BatchFile::factory()->create();

        $this->postJson('/api/openai/v1/batches', [
            'input_file_id' => $file->id,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);

        $this->assertDatabaseHas('local_batch_api_batches', [
            'provider_format' => 'openai',
            'status' => BatchStatus::Pending->value,
            'input_file_id' => $file->id,
        ]);
    }

    public function test_dispatches_process_batch_job(): void
    {
        Queue::fake();
        $file = BatchFile::factory()->create();

        $this->postJson('/api/openai/v1/batches', [
            'input_file_id' => $file->id,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);

        Queue::assertPushed(ProcessBatchJob::class);
    }

    public function test_validates_input_file_id_exists(): void
    {
        $this->postJson('/api/openai/v1/batches', [
            'input_file_id' => 'file-nonexistent',
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['input_file_id']);
    }

    public function test_validates_required_fields(): void
    {
        $this->postJson('/api/openai/v1/batches', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['input_file_id', 'endpoint', 'completion_window']);
    }

    public function test_fires_batch_created_event(): void
    {
        Event::fake();
        Queue::fake();
        $file = BatchFile::factory()->create();

        $this->postJson('/api/openai/v1/batches', [
            'input_file_id' => $file->id,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);

        Event::assertDispatched(BatchCreatedEvent::class, fn ($e) => $e->providerFormat === 'openai');
    }
}
