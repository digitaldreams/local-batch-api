<?php

namespace BatchApi\Anthropic\Batches\SubmitBatch;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use BatchApi\Shared\Batch\NormalizeAnthropicRequestService;
use BatchApi\Shared\Batch\ProcessBatchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SubmitAnthropicBatchController extends Controller
{
    public function __construct(
        private readonly NormalizeAnthropicRequestService $normalizer
    ) {}

    public function store(SubmitAnthropicBatchRequest $request): JsonResponse
    {
        $payload = $this->normalizer->normalize($request->validated('requests'));

        $batch = Batch::create([
            'provider_format' => 'anthropic',
            'status'          => BatchStatus::Pending,
            'payload'         => $payload,
            'request_count'   => count($payload),
            'expires_at'      => now()->addHours(24),
        ]);

        ProcessBatchJob::dispatch($batch->id);

        return (new AnthropicBatchResource($batch))
            ->response()
            ->setStatusCode(202);
    }
}
