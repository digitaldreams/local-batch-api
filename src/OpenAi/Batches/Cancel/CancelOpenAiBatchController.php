<?php

namespace BatchApi\OpenAi\Batches\Cancel;

use BatchApi\BatchService;
use BatchApi\OpenAi\Batches\SubmitBatch\OpenAiBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class CancelOpenAiBatchController extends Controller
{
    public function __construct(private readonly BatchService $batchService) {}

    public function store(Batch $batch): JsonResponse|OpenAiBatchResource
    {
        try {
            return new OpenAiBatchResource($this->batchService->cancelBatch($batch->id));
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
