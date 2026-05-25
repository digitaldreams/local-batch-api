<?php

namespace BatchApi\Anthropic\Batches\List;

use BatchApi\Anthropic\Batches\SubmitBatch\AnthropicBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAnthropicBatchesController
{
    public function index(): AnonymousResourceCollection
    {
        $batches = Batch::where('provider_format', 'anthropic')
            ->latest()
            ->paginate(20);

        return AnthropicBatchResource::collection($batches);
    }
}
