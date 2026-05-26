<?php

namespace BatchApi\Events;

final class CancelBatchEvent
{
    public function __construct(public readonly string $batchId) {}
}
