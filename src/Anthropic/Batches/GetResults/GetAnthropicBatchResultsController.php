<?php

namespace BatchApi\Anthropic\Batches\GetResults;

use BatchApi\Data\BatchResultDto;
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

        $results = array_map(fn ($r) => BatchResultDto::fromArray($r), $batch->raw_response);

        return response()->stream(function () use ($results): void {
            foreach ($results as $result) {
                echo json_encode($result->toAnthropicNdjson())."\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
