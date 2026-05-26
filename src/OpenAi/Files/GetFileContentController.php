<?php

namespace BatchApi\OpenAi\Files;

use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GetFileContentController extends Controller
{
    public function show(BatchFile $batchFile): StreamedResponse|Response
    {
        if (! $batchFile->content) {
            return response('', 204);
        }

        $content = $batchFile->content;
        $filename = "{$batchFile->id}.jsonl";

        return response()->stream(function () use ($content): void {
            echo $content;
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
