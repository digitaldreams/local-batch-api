<?php

namespace BatchApi\Events;

final class CancelBatch
{
    public function __construct(public readonly string $batchId) {}
}
