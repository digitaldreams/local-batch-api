<?php

namespace BatchApi\Listeners;

use BatchApi\BatchService;
use BatchApi\Events\SubmitAnthropicBatchEvent;

class HandleSubmitAnthropicBatchListener
{
    public function __construct(private readonly BatchService $service) {}

    public function handle(SubmitAnthropicBatchEvent $event): void
    {
        $this->service->submitAnthropicBatch($event->items);
    }
}
