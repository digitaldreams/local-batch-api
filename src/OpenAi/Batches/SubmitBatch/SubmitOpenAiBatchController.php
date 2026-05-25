<?php

namespace BatchApi\OpenAi\Batches\SubmitBatch;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use BatchApi\Shared\Batch\Models\BatchFile;
use BatchApi\Shared\Batch\NormalizeOpenAiRequestService;
use BatchApi\Shared\Batch\ProcessBatchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubmitOpenAiBatchController extends Controller
{
    public function __construct(
        private readonly NormalizeOpenAiRequestService $normalizer
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'input_file_id'     => ['required', 'string', 'exists:batch_files,id'],
            'endpoint'          => ['required', 'string'],
            'completion_window' => ['required', 'string'],
        ]);

        $file    = BatchFile::findOrFail($request->input('input_file_id'));
        $payload = $this->normalizer->normalize($file->content);

        $batch = Batch::create([
            'provider_format' => 'openai',
            'status'          => BatchStatus::Pending,
            'payload'         => $payload,
            'input_file_id'   => $file->id,
            'request_count'   => count($payload),
            'expires_at'      => now()->addHours(24),
        ]);

        ProcessBatchJob::dispatch($batch->id);

        return (new OpenAiBatchResource($batch))
            ->response()
            ->setStatusCode(201);
    }
}
