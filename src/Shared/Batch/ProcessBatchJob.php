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

        if (! $batch || $batch->status !== BatchStatus::Pending) {
            return;
        }

        $batch->update(['status' => BatchStatus::Processing]);

        $baseUrl   = config('ollama.url', 'http://localhost:11434');
        $default   = config('ollama.model', 'llama3.2');
        $keepAlive = config('ollama.keep_alive', '45m');

        $responses = Http::pool(function (Pool $pool) use ($batch, $baseUrl, $default, $keepAlive): void {
            foreach ($batch->payload as $req) {
                $pool->as($req['custom_id'])
                    ->timeout(10000)
                    ->post("{$baseUrl}/api/chat", [
                        'model'      => $req['model'] ?? $default,
                        'messages'   => $req['messages'],
                        'stream'     => false,
                        'keep_alive' => $keepAlive,
                        'options'    => ['num_predict' => $req['max_tokens'] ?? 2048],
                    ]);
            }
        });

        $results   = [];
        $succeeded = 0;
        $errored   = 0;

        foreach ($batch->payload as $req) {
            $r = $responses[$req['custom_id']] ?? null;

            if ($r instanceof \Throwable || ! $r?->successful()) {
                $results[] = [
                    'custom_id'     => $req['custom_id'],
                    'succeeded'     => false,
                    'content'       => null,
                    'input_tokens'  => 0,
                    'output_tokens' => 0,
                    'error'         => $r instanceof \Throwable ? $r->getMessage() : "HTTP {$r->status()}",
                ];
                $errored++;
            } else {
                $json      = $r->json();
                $results[] = [
                    'custom_id'     => $req['custom_id'],
                    'succeeded'     => true,
                    'content'       => $json['message']['content'] ?? null,
                    'input_tokens'  => $json['prompt_eval_count'] ?? 0,
                    'output_tokens' => $json['eval_count'] ?? 0,
                    'error'         => null,
                ];
                $succeeded++;
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
     * @param  array<int, array{custom_id: string, succeeded: bool, content: string|null, input_tokens: int, output_tokens: int, error: string|null}>  $results
     */
    private function toOpenAiJsonl(array $results): string
    {
        return implode("\n", array_map(function (array $result): string {
            if (! $result['succeeded']) {
                return json_encode([
                    'custom_id' => $result['custom_id'],
                    'response'  => null,
                    'error'     => ['message' => $result['error']],
                ]);
            }

            return json_encode([
                'custom_id' => $result['custom_id'],
                'response'  => [
                    'status_code' => 200,
                    'body'        => [
                        'choices' => [
                            [
                                'message' => [
                                    'role'    => 'assistant',
                                    'content' => $result['content'],
                                ],
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
