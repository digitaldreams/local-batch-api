<?php

namespace BatchApi\Shared\Batch;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

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
            'status'         => BatchStatus::Processing,
            'in_progress_at' => now(),
        ]);

        $baseUrl     = config('ollama.url', 'http://localhost:11434');
        $default     = config('ollama.model', 'llama3.2');
        $keepAlive   = config('ollama.keep_alive', '5m');
        $timeout     = (int) config('ollama.timeout', 120);
        $concurrency = max(1, (int) config('ollama.concurrency', 1));

        $results   = [];
        $succeeded = 0;
        $errored   = 0;

        if ($concurrency === 1) {
            // Sequential: one request → wait → next. Safe on CPU-only hosts.
            foreach ($batch->payload as $req) {
                $this->processRequest($req, $baseUrl, $default, $keepAlive, $timeout, $results, $succeeded, $errored);
            }
        } else {
            // Parallel: fire $concurrency requests at once, wait, repeat.
            foreach (array_chunk($batch->payload, $concurrency) as $chunk) {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $baseUrl, $default, $keepAlive, $timeout): void {
                    foreach ($chunk as $req) {
                        $pool->as($req['custom_id'])
                            ->timeout($timeout)
                            ->post("{$baseUrl}/api/chat", array_filter([
                                'model'      => $req['model'] ?? $default,
                                'messages'   => $req['messages'],
                                'system'     => $req['system'] ?? null,
                                'stream'     => false,
                                'keep_alive' => $keepAlive,
                                'options'    => ['num_predict' => $req['max_tokens'] ?? 2048],
                            ], fn ($v) => $v !== null));
                    }
                });

                foreach ($chunk as $req) {
                    $r = $responses[$req['custom_id']] ?? null;
                    $this->collectResponse($r, $req, $default, $results, $succeeded, $errored);
                }
            }
        }

        $updates = [
            'status'          => BatchStatus::Completed,
            'raw_response'    => $results,
            'succeeded_count' => $succeeded,
            'errored_count'   => $errored,
            'completed_at'    => now(),
        ];

        if ($batch->provider_format === 'openai') {
            $file = BatchFile::create([
                'id'      => 'file-'.str_replace('-', '', substr((string) \Illuminate\Support\Str::uuid(), 0, 16)),
                'purpose' => 'batch_output',
                'content' => $this->toOpenAiJsonl($results),
            ]);
            $updates['output_file_id'] = $file->id;
        }

        $batch->update($updates);
    }

    /**
     * @param  array{custom_id: string, model: string|null, max_tokens: int|null, system: string|null, messages: array<int, array{role: string, content: string}>}  $req
     * @param  array<int, mixed>  $results
     */
    private function processRequest(
        array $req,
        string $baseUrl,
        string $default,
        string $keepAlive,
        int $timeout,
        array &$results,
        int &$succeeded,
        int &$errored,
    ): void {
        try {
            $r = Http::timeout($timeout)->post("{$baseUrl}/api/chat", array_filter([
                'model'      => $req['model'] ?? $default,
                'messages'   => $req['messages'],
                'system'     => $req['system'] ?? null,
                'stream'     => false,
                'keep_alive' => $keepAlive,
                'options'    => ['num_predict' => $req['max_tokens'] ?? 2048],
            ], fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            $results[] = $this->errorResult($req['custom_id'], $e->getMessage());
            $errored++;

            return;
        }

        $this->collectResponse($r, $req, $default, $results, $succeeded, $errored);
    }

    /**
     * @param  \Illuminate\Http\Client\Response|\Throwable|null  $r
     * @param  array{custom_id: string, model: string|null}  $req
     * @param  array<int, mixed>  $results
     */
    private function collectResponse(
        mixed $r,
        array $req,
        string $default,
        array &$results,
        int &$succeeded,
        int &$errored,
    ): void {
        if ($r instanceof \Throwable || ! $r?->successful()) {
            $results[] = $this->errorResult(
                $req['custom_id'],
                $r instanceof \Throwable ? $r->getMessage() : "HTTP {$r->status()}"
            );
            $errored++;

            return;
        }

        $json       = $r->json();
        $doneReason = $json['done_reason'] ?? 'stop';

        $results[] = [
            'custom_id'     => $req['custom_id'],
            'succeeded'     => true,
            'content'       => $json['message']['content'] ?? null,
            'model'         => $json['model'] ?? ($req['model'] ?? $default),
            'stop_reason'   => $doneReason === 'length' ? 'max_tokens' : 'end_turn',
            'input_tokens'  => $json['prompt_eval_count'] ?? 0,
            'output_tokens' => $json['eval_count'] ?? 0,
            'error'         => null,
        ];
        $succeeded++;
    }

    /**
     * @return array{custom_id: string, succeeded: false, content: null, model: null, stop_reason: null, input_tokens: 0, output_tokens: 0, error: string}
     */
    private function errorResult(string $customId, string $error): array
    {
        return [
            'custom_id'     => $customId,
            'succeeded'     => false,
            'content'       => null,
            'model'         => null,
            'stop_reason'   => null,
            'input_tokens'  => 0,
            'output_tokens' => 0,
            'error'         => $error,
        ];
    }

    /**
     * @param  array<int, array{custom_id: string, succeeded: bool, content: string|null, input_tokens: int, output_tokens: int, error: string|null}>  $results
     */
    private function toOpenAiJsonl(array $results): string
    {
        return implode("\n", array_map(function (array $result): string {
            $resultId = 'batch_req_'.str_replace('-', '', substr((string) \Illuminate\Support\Str::uuid(), 0, 16));

            if (! $result['succeeded']) {
                return json_encode([
                    'id'        => $resultId,
                    'custom_id' => $result['custom_id'],
                    'response'  => null,
                    'error'     => ['code' => 'server_error', 'message' => $result['error']],
                ]);
            }

            return json_encode([
                'id'        => $resultId,
                'custom_id' => $result['custom_id'],
                'response'  => [
                    'status_code' => 200,
                    'request_id'  => 'req_'.str_replace('-', '', substr((string) \Illuminate\Support\Str::uuid(), 0, 16)),
                    'body'        => [
                        'id'      => 'chatcmpl-'.str_replace('-', '', substr((string) \Illuminate\Support\Str::uuid(), 0, 16)),
                        'object'  => 'chat.completion',
                        'model'   => $result['model'] ?? 'unknown',
                        'choices' => [
                            [
                                'index'         => 0,
                                'message'       => [
                                    'role'    => 'assistant',
                                    'content' => $result['content'],
                                ],
                                'finish_reason' => $result['stop_reason'] === 'max_tokens' ? 'length' : 'stop',
                            ],
                        ],
                        'usage' => [
                            'prompt_tokens'     => $result['input_tokens'],
                            'completion_tokens' => $result['output_tokens'],
                            'total_tokens'      => $result['input_tokens'] + $result['output_tokens'],
                        ],
                    ],
                ],
                'error' => null,
            ]);
        }, $results));
    }
}
