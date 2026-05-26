<?php

namespace BatchApi\Events;

use BatchApi\Shared\Batch\Models\Batch;

final class BatchProcessing
{
    public function __construct(public readonly Batch $batch) {}
}
