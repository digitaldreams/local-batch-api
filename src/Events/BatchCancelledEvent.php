<?php

namespace BatchApi\Events;

use BatchApi\Shared\Batch\Models\Batch;

final class BatchCancelledEvent
{
    public function __construct(public readonly Batch $batch) {}
}
