<?php

namespace BatchApi\OpenAi\Batches\SubmitBatch;

use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Batch */
class OpenAiBatchResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'object'            => 'batch',
            'endpoint'          => '/v1/chat/completions',
            'status'            => $this->status->openAiStatus(),
            'input_file_id'     => $this->input_file_id,
            'output_file_id'    => $this->output_file_id,
            'completion_window' => '24h',
            'request_counts'    => [
                'total'     => $this->request_count,
                'completed' => $this->succeeded_count,
                'failed'    => $this->errored_count,
            ],
            'created_at'   => $this->created_at->timestamp,
            'completed_at' => $this->completed_at?->timestamp,
            'expires_at'   => $this->expires_at?->timestamp,
        ];
    }
}
