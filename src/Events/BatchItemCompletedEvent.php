<?php

namespace BatchApi\Events;

use BatchApi\Data\BatchResultDto;
use BatchApi\Shared\Batch\Models\Batch;

final class BatchItemCompletedEvent
{
    public function __construct(
        public readonly Batch $batch,
        public readonly BatchResultDto $result,
    ) {}
}
