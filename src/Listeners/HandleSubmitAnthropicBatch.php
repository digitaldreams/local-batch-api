<?php

namespace BatchApi\Listeners;

use BatchApi\BatchService;
use BatchApi\Events\SubmitAnthropicBatch;

class HandleSubmitAnthropicBatch
{
    public function __construct(private readonly BatchService $service) {}

    public function handle(SubmitAnthropicBatch $event): void
    {
        $this->service->submitAnthropicBatch($event->items);
    }
}
