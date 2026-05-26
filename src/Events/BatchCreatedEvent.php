<?php

namespace BatchApi\Events;

use BatchApi\Data\Input\AnthropicBatchItemDto;
use BatchApi\Data\Input\OpenAiBatchItemDto;
use BatchApi\Shared\Batch\Models\Batch;

final class BatchCreatedEvent
{
    /** @param AnthropicBatchItemDto[]|OpenAiBatchItemDto[] $items */
    public function __construct(
        public readonly Batch $batch,
        public readonly array $items,
        public readonly string $providerFormat,
    ) {
        foreach ($items as $item) {
            if (! $item instanceof AnthropicBatchItemDto && ! $item instanceof OpenAiBatchItemDto) {
                throw new \InvalidArgumentException('Items must be AnthropicBatchItemDto or OpenAiBatchItemDto instances.');
            }
        }
    }
}
