<?php

namespace BatchApi\Listeners;

use BatchApi\BatchService;
use BatchApi\Events\CancelBatchEvent;

class HandleCancelBatchListener
{
    public function __construct(private readonly BatchService $service) {}

    public function handle(CancelBatchEvent $event): void
    {
        $this->service->cancelBatch($event->batchId);
    }
}
