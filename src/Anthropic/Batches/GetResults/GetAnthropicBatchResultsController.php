<?php

namespace BatchApi\Anthropic\Batches\GetResults;

use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GetAnthropicBatchResultsController extends Controller
{
    public function show(Batch $batch): StreamedResponse|Response
    {
        if (! $batch->raw_response) {
            return response('', 204);
        }

        return response()->stream(function () use ($batch): void {
            foreach ($batch->raw_response as $result) {
                echo json_encode($this->formatResult($result))."\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'      => 'application/x-ndjson',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array{custom_id: string, succeeded: bool, content: string|null, input_tokens: int, output_tokens: int, error: string|null}  $result
     * @return array<string, mixed>
     */
    private function formatResult(array $result): array
    {
        if (! $result['succeeded']) {
            return [
                'custom_id' => $result['custom_id'],
                'result'    => [
                    'type'  => 'errored',
                    'error' => [
                        'type'    => 'server_error',
                        'message' => $result['error'],
                    ],
                ],
            ];
        }

        return [
            'custom_id' => $result['custom_id'],
            'result'    => [
                'type'    => 'succeeded',
                'message' => [
                    'role'    => 'assistant',
                    'content' => [
                        ['type' => 'text', 'text' => $result['content']],
                    ],
                    'usage'   => [
                        'input_tokens'  => $result['input_tokens'],
                        'output_tokens' => $result['output_tokens'],
                    ],
                ],
            ],
        ];
    }
}
