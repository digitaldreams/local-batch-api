<?php

namespace BatchApi\Anthropic\Batches\Cancel;

use BatchApi\Anthropic\Batches\SubmitBatch\AnthropicBatchResource;
use BatchApi\BatchService;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class CancelAnthropicBatchController extends Controller
{
    public function __construct(private readonly BatchService $batchService) {}

    public function store(Batch $batch): JsonResponse|AnthropicBatchResource
    {
        try {
            return new AnthropicBatchResource($this->batchService->cancelBatch($batch->id));
        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => $e->validator->errors()->first(),
                ],
            ], 422);
        }
    }
}
