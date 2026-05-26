<?php

namespace Tests\Feature\Anthropic;

use BatchApi\Events\BatchCreatedEvent;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\ProcessBatchJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmitBatchTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'requests' => [
                [
                    'custom_id' => 'req-1',
                    'params' => [
                        'model' => 'llama3.2',
                        'max_tokens' => 1024,
                        'messages' => [
                            ['role' => 'user', 'content' => 'Hello world'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_returns_202_with_batch_resource(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/anthropic/v1/messages/batches', $this->validPayload());

        $response->assertStatus(202)
            ->assertJsonStructure([
                'id',
                'type',
                'processing_status',
                'request_counts',
                'created_at',
                'expires_at',
                'results_url',
            ])
            ->assertJsonPath('type', 'message_batch')
            ->assertJsonPath('processing_status', 'in_progress');
    }

    public function test_stores_batch_in_database(): void
    {
        Queue::fake();

        $this->postJson('/api/anthropic/v1/messages/batches', $this->validPayload());

        $this->assertDatabaseHas('batches', [
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Pending->value,
            'request_count' => 1,
        ]);
    }

    public function test_dispatches_process_batch_job(): void
    {
        Queue::fake();

        $this->postJson('/api/anthropic/v1/messages/batches', $this->validPayload());

        Queue::assertPushed(ProcessBatchJob::class);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/anthropic/v1/messages/batches', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['requests']);
    }

    public function test_validates_messages_required_in_params(): void
    {
        $response = $this->postJson('/api/anthropic/v1/messages/batches', [
            'requests' => [
                ['custom_id' => 'req-1', 'params' => []],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['requests.0.params.messages']);
    }

    public function test_validates_custom_id_rejects_special_characters(): void
    {
        Queue::fake();

        $payload = $this->validPayload();
        $payload['requests'][0]['custom_id'] = 'invalid id with spaces!';

        $this->postJson('/api/anthropic/v1/messages/batches', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['requests.0.custom_id']);
    }

    public function test_validates_custom_id_rejects_over_64_characters(): void
    {
        Queue::fake();

        $payload = $this->validPayload();
        $payload['requests'][0]['custom_id'] = str_repeat('a', 65);

        $this->postJson('/api/anthropic/v1/messages/batches', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['requests.0.custom_id']);
    }

    public function test_validates_max_tokens_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['requests'][0]['params']['max_tokens']);

        $this->postJson('/api/anthropic/v1/messages/batches', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['requests.0.params.max_tokens']);
    }

    public function test_validates_message_role_must_be_valid(): void
    {
        $payload = $this->validPayload();
        $payload['requests'][0]['params']['messages'][0]['role'] = 'robot';

        $this->postJson('/api/anthropic/v1/messages/batches', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['requests.0.params.messages.0.role']);
    }

    public function test_collects_validation_errors_across_all_requests(): void
    {
        $response = $this->postJson('/api/anthropic/v1/messages/batches', [
            'requests' => [
                ['custom_id' => 'ok', 'params' => ['max_tokens' => 512, 'messages' => [['role' => 'user', 'content' => 'Hi']]]],
                ['custom_id' => 'bad', 'params' => []],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['requests.1.params.messages']);
    }

    public function test_request_count_matches_number_of_requests(): void
    {
        Queue::fake();

        $payload = [
            'requests' => [
                ['custom_id' => 'req-1', 'params' => ['max_tokens' => 512, 'messages' => [['role' => 'user', 'content' => 'A']]]],
                ['custom_id' => 'req-2', 'params' => ['max_tokens' => 512, 'messages' => [['role' => 'user', 'content' => 'B']]]],
                ['custom_id' => 'req-3', 'params' => ['max_tokens' => 512, 'messages' => [['role' => 'user', 'content' => 'C']]]],
            ],
        ];

        $this->postJson('/api/anthropic/v1/messages/batches', $payload);

        $this->assertDatabaseHas('batches', ['request_count' => 3]);
    }

    public function test_fires_batch_created_event(): void
    {
        Event::fake();

        $this->postJson('/api/anthropic/v1/messages/batches', $this->validPayload());

        Event::assertDispatched(BatchCreatedEvent::class, fn ($e) => $e->providerFormat === 'anthropic');
    }

    public function test_accepts_system_prompt(): void
    {
        Queue::fake();

        $payload = $this->validPayload();
        $payload['requests'][0]['params']['system'] = 'You are a pirate.';

        $this->postJson('/api/anthropic/v1/messages/batches', $payload)
            ->assertStatus(202);
    }
}
