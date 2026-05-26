<?php

namespace BatchApi\Listeners;

use BatchApi\BatchService;
use BatchApi\Events\CancelBatch;

class HandleCancelBatch
{
    public function __construct(private readonly BatchService $service) {}

    public function handle(CancelBatch $event): void
    {
        $this->service->cancelBatch($event->batchId);
    }
}
