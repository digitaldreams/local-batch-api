<?php

namespace BatchApi\Shared\Batch;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Data\Input\AnthropicBatchItemDto;

class NormalizeAnthropicRequestService
{
    /**
     * @param  AnthropicBatchItemDto[]  $items
     * @return BatchRequestDto[]
     */
    public function normalize(array $items): array
    {
        return array_map(fn (AnthropicBatchItemDto $item) => new BatchRequestDto(
            customId: $item->customId,
            messages: array_map(
                fn (array $msg) => [
                    'role' => $msg['role'],
                    'content' => $this->flattenContent($msg['content']),
                ],
                $item->messages
            ),
            maxTokens: $item->maxTokens,
            model: $item->model,
            system: $item->system !== null ? $this->flattenContent($item->system) : null,
        ), $items);
    }

    /**
     * Anthropic content is string OR array of content blocks.
     * Inference backends only accept string — extract text blocks and join.
     *
     * @param  string|array<int, array{type: string, text?: string}>  $content
     */
    private function flattenContent(string|array $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        return implode("\n", array_filter(
            array_map(fn (array $block) => $block['type'] === 'text' ? ($block['text'] ?? '') : null, $content)
        ));
    }
}
