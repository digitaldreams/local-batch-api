<?php

namespace BatchApi\Events;

use BatchApi\Data\BatchResultDto;
use BatchApi\Shared\Batch\Models\Batch;

final class BatchCompleted
{
    /** @param BatchResultDto[] $results */
    public function __construct(
        public readonly Batch $batch,
        public readonly array $results,
    ) {
        foreach ($results as $result) {
            if (! $result instanceof BatchResultDto) {
                throw new \InvalidArgumentException('Results must be BatchResultDto instances.');
            }
        }
    }
}
