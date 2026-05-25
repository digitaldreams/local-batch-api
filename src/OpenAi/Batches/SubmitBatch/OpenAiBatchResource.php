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
            'errors'            => null,
            'input_file_id'     => $this->input_file_id,
            'completion_window' => '24h',
            'status'            => $this->status->openAiStatus(),
            'output_file_id'    => $this->output_file_id,
            'error_file_id'     => null,
            'created_at'        => $this->created_at->timestamp,
            'in_progress_at'    => $this->in_progress_at?->timestamp,
            'expires_at'        => $this->expires_at?->timestamp,
            'completed_at'      => $this->completed_at?->timestamp,
            'failed_at'         => null,
            'expired_at'        => null,
            'request_counts'    => [
                'total'     => $this->request_count,
                'completed' => $this->succeeded_count,
                'failed'    => $this->errored_count,
            ],
            'metadata' => null,
        ];
    }
}
