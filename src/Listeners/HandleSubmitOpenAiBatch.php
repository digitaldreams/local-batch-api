<?php

namespace BatchApi\Listeners;

use BatchApi\BatchService;
use BatchApi\Events\SubmitOpenAiBatch;

class HandleSubmitOpenAiBatch
{
    public function __construct(private readonly BatchService $service) {}

    public function handle(SubmitOpenAiBatch $event): void
    {
        $this->service->submitOpenAiBatch($event->inputFileId, $event->items);
    }
}
