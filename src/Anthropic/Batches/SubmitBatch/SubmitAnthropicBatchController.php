<?php

namespace BatchApi\Anthropic\Batches\SubmitBatch;

use BatchApi\BatchService;
use BatchApi\Data\Input\AnthropicBatchItemDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubmitAnthropicBatchController extends Controller
{
    public function __construct(private readonly BatchService $batchService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['requests' => ['required', 'array', 'min:1']]);

        $items = AnthropicBatchItemDto::fromCollection($request->input('requests'));
        $batch = $this->batchService->submitAnthropicBatch($items);

        return (new AnthropicBatchResource($batch))
            ->response()
            ->setStatusCode(202);
    }
}
