<?php

namespace BatchApi\OpenAi\Batches\List;

use BatchApi\OpenAi\Batches\SubmitBatch\OpenAiBatchResource;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListOpenAiBatchesController
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) ($request->query('limit', 20)), 100);
        $afterId = $request->query('after');

        $query = Batch::where('provider_format', 'openai')->orderByDesc('created_at');

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
            'object' => 'list',
            'data' => OpenAiBatchResource::collection($batches)->resolve(),
            'has_more' => $hasMore,
            'first_id' => $batches->first()?->id,
            'last_id' => $batches->last()?->id,
        ]);
    }
}
