<?php

namespace Tests\Unit;

use BatchApi\Data\Input\AnthropicBatchItemDto;
use BatchApi\Shared\Batch\NormalizeAnthropicRequestService;
use PHPUnit\Framework\TestCase;

class NormalizeAnthropicRequestServiceTest extends TestCase
{
    private NormalizeAnthropicRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NormalizeAnthropicRequestService;
    }

    public function test_normalizes_string_content_messages(): void
    {
        $items = [new AnthropicBatchItemDto(
            customId: 'req-1',
            maxTokens: 512,
            messages: [['role' => 'user', 'content' => 'Hello']],
        )];

        $result = $this->service->normalize($items);

        $this->assertCount(1, $result);
        $this->assertSame('req-1', $result[0]->customId);
        $this->assertSame('Hello', $result[0]->messages[0]['content']);
        $this->assertSame(512, $result[0]->maxTokens);
    }

    public function test_flattens_array_content_blocks_to_string(): void
    {
        $items = [new AnthropicBatchItemDto(
            customId: 'req-1',
            maxTokens: 512,
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'First part'],
                    ['type' => 'text', 'text' => 'Second part'],
                ],
            ]],
        )];

        $result = $this->service->normalize($items);

        $this->assertSame("First part\nSecond part", $result[0]->messages[0]['content']);
    }

    public function test_skips_non_text_content_blocks(): void
    {
        $items = [new AnthropicBatchItemDto(
            customId: 'req-1',
            maxTokens: 512,
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => []],
                    ['type' => 'text', 'text' => 'Describe this'],
                ],
            ]],
        )];

        $result = $this->service->normalize($items);

        $this->assertSame('Describe this', $result[0]->messages[0]['content']);
    }

    public function test_flattens_system_string(): void
    {
        $items = [new AnthropicBatchItemDto(
            customId: 'req-1',
            maxTokens: 512,
            messages: [['role' => 'user', 'content' => 'Hello']],
            system: 'You are a pirate.',
        )];

        $result = $this->service->normalize($items);

        $this->assertSame('You are a pirate.', $result[0]->system);
    }

    public function test_flattens_system_array_content_blocks(): void
    {
        $items = [new AnthropicBatchItemDto(
            customId: 'req-1',
            maxTokens: 512,
            messages: [['role' => 'user', 'content' => 'Hello']],
            system: [['type' => 'text', 'text' => 'Speak like a pirate.']],
        )];

        $result = $this->service->normalize($items);

        $this->assertSame('Speak like a pirate.', $result[0]->system);
    }

    public function test_null_system_stays_null(): void
    {
        $items = [new AnthropicBatchItemDto(
            customId: 'req-1',
            maxTokens: 512,
            messages: [['role' => 'user', 'content' => 'Hello']],
        )];

        $result = $this->service->normalize($items);

        $this->assertNull($result[0]->system);
    }

    public function test_normalizes_multiple_items(): void
    {
        $items = [
            new AnthropicBatchItemDto(customId: 'req-1', maxTokens: 100, messages: [['role' => 'user', 'content' => 'A']]),
            new AnthropicBatchItemDto(customId: 'req-2', maxTokens: 200, messages: [['role' => 'user', 'content' => 'B']]),
        ];

        $result = $this->service->normalize($items);

        $this->assertCount(2, $result);
        $this->assertSame('req-1', $result[0]->customId);
        $this->assertSame('req-2', $result[1]->customId);
    }
}
