<?php

namespace BatchApi\OpenAi\Batches\SubmitBatch;

use BatchApi\BatchService;
use BatchApi\Data\Input\OpenAiBatchItemDto;
use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubmitOpenAiBatchController extends Controller
{
    public function __construct(private readonly BatchService $batchService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'input_file_id' => ['required', 'string', 'exists:batch_files,id'],
            'endpoint' => ['required', 'string'],
            'completion_window' => ['required', 'string'],
        ]);

        $file = BatchFile::findOrFail($request->input('input_file_id'));
        $items = OpenAiBatchItemDto::fromJsonl($file->content);
        $batch = $this->batchService->submitOpenAiBatch($file->id, $items);

        return (new OpenAiBatchResource($batch))
            ->response()
            ->setStatusCode(201);
    }
}
