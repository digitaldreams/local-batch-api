<?php

namespace BatchApi\Anthropic\Batches\List;

use BatchApi\Anthropic\Batches\SubmitBatch\AnthropicBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListAnthropicBatchesController
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) ($request->query('limit', 20)), 100);
        $beforeId = $request->query('before_id');
        $afterId = $request->query('after_id');

        $query = Batch::where('provider_format', 'anthropic')->orderByDesc('created_at');

        if ($beforeId) {
            $pivot = Batch::find($beforeId);
            if ($pivot) {
                $query->where('created_at', '>', $pivot->created_at);
            }
        }

        if ($afterId) {
            $pivot = Batch::find($afterId);
            if ($pivot) {
                $query->where('created_at', '<', $pivot->created_at);
            }
        }

        // Fetch one extra to determine has_more
        $batches = $query->limit($limit + 1)->get();
        $hasMore = $batches->count() > $limit;

        if ($hasMore) {
            $batches->pop();
        }

        return response()->json([
            'data' => AnthropicBatchResource::collection($batches)->resolve(),
            'has_more' => $hasMore,
            'first_id' => $batches->first()?->id,
            'last_id' => $batches->last()?->id,
        ]);
    }
}
