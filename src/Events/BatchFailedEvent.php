<?php

namespace BatchApi\Events;

use BatchApi\Shared\Batch\Models\Batch;

final class BatchFailedEvent
{
    public function __construct(
        public readonly Batch $batch,
        public readonly \Throwable $exception,
    ) {}
}
