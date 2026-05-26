<?php

namespace BatchApi\Shared\Batch;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Data\Input\OpenAiBatchItemDto;

class NormalizeOpenAiRequestService
{
    /**
     * @param  OpenAiBatchItemDto[]  $items
     * @return BatchRequestDto[]
     */
    public function normalize(array $items): array
    {
        return array_map(fn (OpenAiBatchItemDto $item) => new BatchRequestDto(
            customId: $item->customId,
            messages: $item->messages,
            maxTokens: $item->maxTokens ?? 2048,
            model: $item->model,
        ), $items);
    }
}
