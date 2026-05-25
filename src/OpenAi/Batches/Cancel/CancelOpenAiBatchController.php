<?php

namespace BatchApi\OpenAi\Batches\Cancel;

use BatchApi\OpenAi\Batches\SubmitBatch\OpenAiBatchResource;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CancelOpenAiBatchController extends Controller
{
    public function store(Batch $batch): JsonResponse|OpenAiBatchResource
    {
        if ($batch->status->isTerminal()) {
            return response()->json([
                'error' => [
                    'type'    => 'invalid_request_error',
                    'message' => 'Batch has already ended and cannot be cancelled.',
                ],
            ], 422);
        }

        $batch->update(['status' => BatchStatus::Cancelled]);

        return new OpenAiBatchResource($batch->fresh());
    }
}
