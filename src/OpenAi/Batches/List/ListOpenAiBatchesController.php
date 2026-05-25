<?php

namespace BatchApi\OpenAi\Batches\List;

use BatchApi\OpenAi\Batches\SubmitBatch\OpenAiBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListOpenAiBatchesController
{
    public function index(): AnonymousResourceCollection
    {
        $batches = Batch::where('provider_format', 'openai')
            ->latest()
            ->paginate(20);

        return OpenAiBatchResource::collection($batches);
    }
}
