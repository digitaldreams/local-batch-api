<?php

namespace BatchApi\Anthropic\Batches\SubmitBatch;

use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Batch */
class AnthropicBatchResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'type'              => 'ollama_message_batch',
            'processing_status' => $this->status->anthropicProcessingStatus(),
            'request_counts'    => [
                'processing' => $this->status->anthropicProcessingStatus() === 'in_progress'
                    ? $this->request_count - $this->succeeded_count - $this->errored_count
                    : 0,
                'succeeded'  => $this->succeeded_count,
                'errored'    => $this->errored_count,
                'canceled'   => 0,
                'expired'    => 0,
            ],
            'created_at'  => $this->created_at->toIso8601String(),
            'expires_at'  => $this->expires_at?->toIso8601String(),
            'ended_at'    => $this->completed_at?->toIso8601String(),
            'results_url' => "/api/anthropic/v1/messages/batches/{$this->id}/results",
        ];
    }
}
