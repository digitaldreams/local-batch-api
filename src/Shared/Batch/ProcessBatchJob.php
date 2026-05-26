<?php

namespace BatchApi\Shared\Batch;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Events\BatchCompletedEvent;
use BatchApi\Events\BatchFailedEvent;
use BatchApi\Events\BatchItemCompletedEvent;
use BatchApi\Events\BatchItemStartedEvent;
use BatchApi\Events\BatchProcessingEvent;
use BatchApi\Inference\InferenceAdapterFactory;
use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProcessBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(private readonly string $batchId) {}

    public function handle(): void
    {
        $batch = Batch::find($this->batchId);

        if (! $batch) {
            return;
        }

        if ($batch->status === BatchStatus::Cancelling) {
            $batch->update(['status' => BatchStatus::Cancelled]);

            return;
        }

        if ($batch->status !== BatchStatus::Pending) {
            return;
        }

        $batch->update([
            'status' => BatchStatus::Processing,
            'in_progress_at' => now(),
        ]);

        event(new BatchProcessingEvent($batch->fresh()));

        $adapter = InferenceAdapterFactory::make();
        $concurrency = max(1, (int) config('inference.concurrency', 1));
        $dtos = array_map(fn ($row) => BatchRequestDto::fromArray($row), $batch->payload);
        $results = [];

        if ($concurrency === 1) {
            foreach ($dtos as $dto) {
                event(new BatchItemStartedEvent($batch, $dto));
                $result = $adapter->chat($dto);
                $results[] = $result;
                event(new BatchItemCompletedEvent($batch, $result));
            }
        } else {
            foreach (array_chunk($dtos, $concurrency) as $chunk) {
                foreach ($chunk as $dto) {
                    event(new BatchItemStartedEvent($batch, $dto));
                }

                $responses = Http::pool(function (Pool $pool) use ($chunk, $adapter): void {
                    foreach ($chunk as $dto) {
                        $adapter->poolRequest($pool, $dto);
                    }
                });

                foreach ($chunk as $dto) {
                    $result = $adapter->parsePoolResponse($responses[$dto->customId] ?? null, $dto);
                    $results[] = $result;
                    event(new BatchItemCompletedEvent($batch, $result));
                }
            }
        }

        $succeeded = count(array_filter($results, fn ($r) => $r->succeeded));
        $errored = count($results) - $succeeded;

        $updates = [
            'status' => BatchStatus::Completed,
            'raw_response' => array_map(fn ($r) => $r->toArray(), $results),
            'succeeded_count' => $succeeded,
            'errored_count' => $errored,
            'completed_at' => now(),
        ];

        if ($batch->provider_format === 'openai') {
            $file = BatchFile::create([
                'id' => 'file-'.Str::replace('-', '', substr((string) Str::uuid(), 0, 16)),
                'purpose' => 'batch_output',
                'content' => implode("\n", array_map(fn ($r) => json_encode($r->toOpenAiJsonl()), $results)),
            ]);
            $updates['output_file_id'] = $file->id;
        }

        $batch->update($updates);

        event(new BatchCompletedEvent($batch->fresh(), $results));
    }

    public function failed(\Throwable $e): void
    {
        $batch = Batch::find($this->batchId);

        if ($batch) {
            $batch->update(['status' => BatchStatus::Failed]);
            event(new BatchFailedEvent($batch->fresh(), $e));
        }
    }
}
