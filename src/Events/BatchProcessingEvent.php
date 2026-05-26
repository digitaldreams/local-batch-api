<?php

namespace BatchApi\Events;

use BatchApi\Shared\Batch\Models\Batch;

final class BatchProcessingEvent
{
    public function __construct(public readonly Batch $batch) {}
}
