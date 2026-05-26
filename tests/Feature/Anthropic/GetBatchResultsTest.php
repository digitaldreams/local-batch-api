<?php

namespace Tests\Feature\Anthropic;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetBatchResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_204_when_no_results_yet(): void
    {
        $batch = Batch::factory()->create([
            'status' => BatchStatus::Processing,
            'raw_response' => null,
        ]);

        $this->getJson("/api/anthropic/v1/messages/batches/{$batch->id}/results")
            ->assertNoContent();
    }

    public function test_streams_ndjson_results(): void
    {
        $batch = Batch::factory()->create([
            'status' => BatchStatus::Completed,
            'raw_response' => [
                [
                    'custom_id' => 'req-1',
                    'succeeded' => true,
                    'content' => 'Hello back',
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                    'error' => null,
                ],
            ],
        ]);

        $response = $this->get("/api/anthropic/v1/messages/batches/{$batch->id}/results");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/x-ndjson');

        $line = json_decode($response->streamedContent(), true);
        $this->assertSame('req-1', $line['custom_id']);
        $this->assertSame('succeeded', $line['result']['type']);
        $this->assertSame('Hello back', $line['result']['message']['content'][0]['text']);
    }

    public function test_streams_errored_result(): void
    {
        $batch = Batch::factory()->create([
            'status' => BatchStatus::Completed,
            'raw_response' => [
                [
                    'custom_id' => 'req-2',
                    'succeeded' => false,
                    'content' => null,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'error' => 'Ollama timeout',
                ],
            ],
        ]);

        $response = $this->get("/api/anthropic/v1/messages/batches/{$batch->id}/results");

        $response->assertOk();
        $line = json_decode($response->streamedContent(), true);
        $this->assertSame('errored', $line['result']['type']);
        $this->assertSame('Ollama timeout', $line['result']['error']['message']);
    }
}
