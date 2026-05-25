<?php

namespace BatchApi\OpenAi\Batches\GetBatch;

use BatchApi\OpenAi\Batches\SubmitBatch\OpenAiBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Routing\Controller;

class GetOpenAiBatchController extends Controller
{
    public function show(Batch $batch): OpenAiBatchResource
    {
        return new OpenAiBatchResource($batch);
    }
}
