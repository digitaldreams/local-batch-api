<?php

namespace BatchApi\Inference;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Data\BatchResultDto;
use Illuminate\Http\Client\Pool;

interface InferenceAdapterInterface
{
    public function chat(BatchRequestDto $request): BatchResultDto;

    public function poolRequest(Pool $pool, BatchRequestDto $request): void;

    public function parsePoolResponse(mixed $response, BatchRequestDto $request): BatchResultDto;
}
