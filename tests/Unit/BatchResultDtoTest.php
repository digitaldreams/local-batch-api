<?php

namespace Tests\Unit;

use BatchApi\Data\BatchResultDto;
use PHPUnit\Framework\TestCase;

class BatchResultDtoTest extends TestCase
{
    private function successDto(string $customId = 'req-1', string $content = 'Hello'): BatchResultDto
    {
        return new BatchResultDto(
            customId: $customId,
            succeeded: true,
            content: $content,
            model: 'llama3.2',
            stopReason: 'end_turn',
            inputTokens: 10,
            outputTokens: 5,
            error: null,
        );
    }

    private function errorDto(string $customId = 'req-1', string $error = 'Timeout'): BatchResultDto
    {
        return new BatchResultDto(
            customId: $customId,
            succeeded: false,
            content: null,
            model: null,
            stopReason: null,
            inputTokens: 0,
            outputTokens: 0,
            error: $error,
        );
    }

    public function test_to_anthropic_ndjson_succeeded(): void
    {
        $dto = $this->successDto('req-1', 'Paris is the capital.');

        $result = $dto->toAnthropicNdjson();

        $this->assertSame('req-1', $result['custom_id']);
        $this->assertSame('succeeded', $result['result']['type']);
        $this->assertSame('message', $result['result']['message']['type']);
        $this->assertSame('assistant', $result['result']['message']['role']);
        $this->assertSame('text', $result['result']['message']['content'][0]['type']);
        $this->assertSame('Paris is the capital.', $result['result']['message']['content'][0]['text']);
        $this->assertSame('end_turn', $result['result']['message']['stop_reason']);
        $this->assertSame(10, $result['result']['message']['usage']['input_tokens']);
        $this->assertSame(5, $result['result']['message']['usage']['output_tokens']);
    }

    public function test_to_anthropic_ndjson_errored(): void
    {
        $dto = $this->errorDto('req-2', 'Ollama timeout');

        $result = $dto->toAnthropicNdjson();

        $this->assertSame('req-2', $result['custom_id']);
        $this->assertSame('errored', $result['result']['type']);
        $this->assertSame('server_error', $result['result']['error']['type']);
        $this->assertSame('Ollama timeout', $result['result']['error']['message']);
    }

    public function test_to_openai_jsonl_succeeded(): void
    {
        $dto = $this->successDto('req-1', 'Hello!');

        $result = $dto->toOpenAiJsonl();

        $this->assertSame('req-1', $result['custom_id']);
        $this->assertNull($result['error']);
        $this->assertSame(200, $result['response']['status_code']);
        $this->assertSame('chat.completion', $result['response']['body']['object']);
        $this->assertSame('Hello!', $result['response']['body']['choices'][0]['message']['content']);
        $this->assertSame('assistant', $result['response']['body']['choices'][0]['message']['role']);
        $this->assertSame('stop', $result['response']['body']['choices'][0]['finish_reason']);
        $this->assertSame(10, $result['response']['body']['usage']['prompt_tokens']);
        $this->assertSame(5, $result['response']['body']['usage']['completion_tokens']);
        $this->assertSame(15, $result['response']['body']['usage']['total_tokens']);
    }

    public function test_to_openai_jsonl_errored(): void
    {
        $dto = $this->errorDto('req-2', 'Connection refused');

        $result = $dto->toOpenAiJsonl();

        $this->assertSame('req-2', $result['custom_id']);
        $this->assertNull($result['response']);
        $this->assertSame('server_error', $result['error']['code']);
        $this->assertSame('Connection refused', $result['error']['message']);
    }

    public function test_to_openai_jsonl_max_tokens_maps_to_length_finish_reason(): void
    {
        $dto = new BatchResultDto(
            customId: 'req-1',
            succeeded: true,
            content: 'truncated',
            model: 'llama3.2',
            stopReason: 'max_tokens',
            inputTokens: 10,
            outputTokens: 100,
            error: null,
        );

        $result = $dto->toOpenAiJsonl();

        $this->assertSame('length', $result['response']['body']['choices'][0]['finish_reason']);
    }

    public function test_from_array_roundtrip(): void
    {
        $dto = $this->successDto('req-1', 'Test content');
        $restored = BatchResultDto::fromArray($dto->toArray());

        $this->assertSame($dto->customId, $restored->customId);
        $this->assertSame($dto->succeeded, $restored->succeeded);
        $this->assertSame($dto->content, $restored->content);
        $this->assertSame($dto->inputTokens, $restored->inputTokens);
        $this->assertSame($dto->outputTokens, $restored->outputTokens);
    }
}
