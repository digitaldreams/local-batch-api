<?php

namespace BatchApi\Listeners;

use BatchApi\BatchService;
use BatchApi\Events\SubmitOpenAiBatchEvent;

class HandleSubmitOpenAiBatchListener
{
    public function __construct(private readonly BatchService $service) {}

    public function handle(SubmitOpenAiBatchEvent $event): void
    {
        $this->service->submitOpenAiBatch($event->inputFileId, $event->items);
    }
}
