<?php

namespace BatchApi\Events;

use BatchApi\Shared\Batch\Models\Batch;

final class BatchFailed
{
    public function __construct(
        public readonly Batch $batch,
        public readonly \Throwable $exception,
    ) {}
}
