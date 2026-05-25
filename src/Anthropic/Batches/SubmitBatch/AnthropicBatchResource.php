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
        $processingStatus = $this->status->anthropicProcessingStatus();
        $isEnded          = $processingStatus === 'ended';

        return [
            'id'                  => $this->id,
            'type'                => 'message_batch',
            'processing_status'   => $processingStatus,
            'request_counts'      => [
                'processing' => $processingStatus === 'in_progress'
                    ? max(0, $this->request_count - $this->succeeded_count - $this->errored_count)
                    : 0,
                'succeeded'  => $this->succeeded_count,
                'errored'    => $this->errored_count,
                'canceled'   => 0,
                'expired'    => 0,
            ],
            'created_at'          => $this->created_at->toIso8601String(),
            'expires_at'          => $this->expires_at?->toIso8601String(),
            'ended_at'            => $this->completed_at?->toIso8601String(),
            'cancel_initiated_at' => $this->cancel_initiated_at?->toIso8601String(),
            'results_url'         => $isEnded
                ? "/api/anthropic/v1/messages/batches/{$this->id}/results"
                : null,
        ];
    }
}
