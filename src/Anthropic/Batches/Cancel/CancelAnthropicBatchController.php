<?php

namespace BatchApi\Anthropic\Batches\Cancel;

use BatchApi\Anthropic\Batches\SubmitBatch\AnthropicBatchResource;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CancelAnthropicBatchController extends Controller
{
    public function store(Batch $batch): JsonResponse|AnthropicBatchResource
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

        return new AnthropicBatchResource($batch->fresh());
    }
}
