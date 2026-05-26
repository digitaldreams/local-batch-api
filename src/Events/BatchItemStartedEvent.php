<?php

namespace BatchApi\Events;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Shared\Batch\Models\Batch;

final class BatchItemStartedEvent
{
    public function __construct(
        public readonly Batch $batch,
        public readonly BatchRequestDto $item,
    ) {}
}
