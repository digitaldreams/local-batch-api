<?php

namespace BatchApi\Shared\Batch;

class NormalizeAnthropicRequestService
{
    /**
     * @param  array<int, array{custom_id: string, params: array{model?: string, max_tokens?: int, messages: array<int, array{role: string, content: string}>}}>  $requests
     * @return array<int, array{custom_id: string, model: string|null, max_tokens: int|null, messages: array<int, array{role: string, content: string}>}>
     */
    public function normalize(array $requests): array
    {
        return array_map(fn (array $req) => [
            'custom_id'  => $req['custom_id'],
            'model'      => $req['params']['model'] ?? null,
            'max_tokens' => $req['params']['max_tokens'] ?? null,
            'messages'   => $req['params']['messages'],
        ], $requests);
    }
}
