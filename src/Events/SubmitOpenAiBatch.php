<?php

namespace BatchApi\Events;

use BatchApi\Data\Input\OpenAiBatchItemDto;

final class SubmitOpenAiBatch
{
    /** @param OpenAiBatchItemDto[] $items */
    public function __construct(
        public readonly string $inputFileId,
        public readonly array $items,
    ) {
        foreach ($items as $item) {
            if (! $item instanceof OpenAiBatchItemDto) {
                throw new \InvalidArgumentException('Items must be OpenAiBatchItemDto instances.');
            }
        }
    }
}
