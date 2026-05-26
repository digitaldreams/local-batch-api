<?php

namespace BatchApi;

use BatchApi\Data\BatchResultDto;
use BatchApi\Data\Input\AnthropicBatchItemDto;
use BatchApi\Data\Input\OpenAiBatchItemDto;
use BatchApi\Events\BatchCancelled;
use BatchApi\Events\BatchCreated;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use BatchApi\Shared\Batch\Models\BatchFile;
use BatchApi\Shared\Batch\NormalizeAnthropicRequestService;
use BatchApi\Shared\Batch\NormalizeOpenAiRequestService;
use BatchApi\Shared\Batch\ProcessBatchJob;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BatchService
{
    public function __construct(
        private readonly NormalizeAnthropicRequestService $anthropicNormalizer,
        private readonly NormalizeOpenAiRequestService $openAiNormalizer,
    ) {}

    /**
     * @param  AnthropicBatchItemDto[]  $items
     */
    public function submitAnthropicBatch(array $items): Batch
    {
        $requests = $this->anthropicNormalizer->normalize($items);

        $batch = Batch::create([
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Pending,
            'payload' => array_map(fn ($r) => $r->toArray(), $requests),
            'request_count' => count($requests),
            'expires_at' => now()->addHours(24),
        ]);

        ProcessBatchJob::dispatch($batch->id);

        event(new BatchCreated($batch, $items, 'anthropic'));

        return $batch;
    }

    /**
     * @param  OpenAiBatchItemDto[]  $items
     */
    public function submitOpenAiBatch(string $inputFileId, array $items): Batch
    {
        $requests = $this->openAiNormalizer->normalize($items);

        $batch = Batch::create([
            'provider_format' => 'openai',
            'status' => BatchStatus::Pending,
            'payload' => array_map(fn ($r) => $r->toArray(), $requests),
            'input_file_id' => $inputFileId,
            'request_count' => count($requests),
            'expires_at' => now()->addHours(24),
        ]);

        ProcessBatchJob::dispatch($batch->id);

        event(new BatchCreated($batch, $items, 'openai'));

        return $batch;
    }

    public function uploadFile(string $jsonlContent, string $purpose = 'batch'): BatchFile
    {
        return BatchFile::create([
            'id' => 'file-'.Str::uuid(),
            'purpose' => $purpose,
            'content' => $jsonlContent,
        ]);
    }

    public function cancelBatch(string $batchId): Batch
    {
        $batch = Batch::findOrFail($batchId);

        if ($batch->status->isTerminal()) {
            throw ValidationException::withMessages([
                'batch' => ['Batch has already ended and cannot be cancelled.'],
            ]);
        }

        $newStatus = $batch->status === BatchStatus::Pending
            ? BatchStatus::Cancelled
            : BatchStatus::Cancelling;

        $batch->update([
            'status' => $newStatus,
            'cancel_initiated_at' => now(),
        ]);

        $fresh = $batch->fresh();

        event(new BatchCancelled($fresh));

        return $fresh;
    }

    public function getBatch(string $batchId): ?Batch
    {
        return Batch::find($batchId);
    }

    /**
     * @return BatchResultDto[]
     */
    public function getResults(string $batchId): array
    {
        $batch = Batch::findOrFail($batchId);

        return array_map(
            fn ($r) => BatchResultDto::fromArray($r),
            $batch->raw_response ?? []
        );
    }
}
