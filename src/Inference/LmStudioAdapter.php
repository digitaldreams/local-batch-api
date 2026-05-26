<?php

namespace BatchApi\Inference;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Data\BatchResultDto;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class LmStudioAdapter implements InferenceAdapterInterface
{
    public function chat(BatchRequestDto $request): BatchResultDto
    {
        try {
            $response = Http::timeout((int) config('inference.timeout', 120))
                ->post($this->url().'/v1/chat/completions', $this->buildBody($request));
        } catch (\Throwable $e) {
            return $this->error($request->customId, $e->getMessage());
        }

        return $this->parsePoolResponse($response, $request);
    }

    public function poolRequest(Pool $pool, BatchRequestDto $request): void
    {
        $pool->as($request->customId)
            ->timeout((int) config('inference.timeout', 120))
            ->post($this->url().'/v1/chat/completions', $this->buildBody($request));
    }

    public function parsePoolResponse(mixed $response, BatchRequestDto $request): BatchResultDto
    {
        if ($response === null || $response instanceof \Throwable || ! $response->successful()) {
            return $this->error($request->customId, match (true) {
                $response === null => 'No response received',
                $response instanceof \Throwable => $response->getMessage(),
                default => "HTTP {$response->status()}",
            });
        }

        $json = $response->json();
        $choice = $json['choices'][0] ?? [];
        $finishReason = $choice['finish_reason'] ?? 'stop';

        return new BatchResultDto(
            customId: $request->customId,
            succeeded: true,
            content: $choice['message']['content'] ?? null,
            model: $json['model'] ?? $request->model ?? config('inference.model', 'llama3.2'),
            stopReason: $finishReason === 'length' ? 'max_tokens' : 'end_turn',
            inputTokens: $json['usage']['prompt_tokens'] ?? 0,
            outputTokens: $json['usage']['completion_tokens'] ?? 0,
            error: null,
        );
    }

    private function buildBody(BatchRequestDto $request): array
    {
        $messages = $request->system
            ? array_merge([['role' => 'system', 'content' => $request->system]], $request->messages)
            : $request->messages;

        return array_filter([
            'model' => $request->model ?? config('inference.model', 'llama3.2'),
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => $request->maxTokens,
        ], fn ($v) => $v !== null);
    }

    private function url(): string
    {
        return rtrim((string) config('inference.url', 'http://localhost:1234'), '/');
    }

    private function error(string $customId, string $message): BatchResultDto
    {
        return new BatchResultDto(
            customId: $customId,
            succeeded: false,
            content: null,
            model: null,
            stopReason: null,
            inputTokens: 0,
            outputTokens: 0,
            error: $message,
        );
    }
}
