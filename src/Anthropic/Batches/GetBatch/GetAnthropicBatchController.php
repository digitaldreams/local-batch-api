<?php

namespace BatchApi\Anthropic\Batches\GetBatch;

use BatchApi\Anthropic\Batches\SubmitBatch\AnthropicBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Routing\Controller;

class GetAnthropicBatchController extends Controller
{
    public function show(Batch $batch): AnthropicBatchResource
    {
        return new AnthropicBatchResource($batch);
    }
}
