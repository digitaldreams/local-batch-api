<?php

namespace BatchApi\Events;

use BatchApi\Data\Input\AnthropicBatchItemDto;

final class SubmitAnthropicBatch
{
    /** @param AnthropicBatchItemDto[] $items */
    public function __construct(public readonly array $items)
    {
        foreach ($items as $item) {
            if (! $item instanceof AnthropicBatchItemDto) {
                throw new \InvalidArgumentException('Items must be AnthropicBatchItemDto instances.');
            }
        }
    }
}
