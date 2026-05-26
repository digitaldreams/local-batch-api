<?php

namespace BatchApi\Inference;

use BatchApi\Data\BatchRequestDto;
use BatchApi\Data\BatchResultDto;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class OllamaAdapter implements InferenceAdapterInterface
{
    public function chat(BatchRequestDto $request): BatchResultDto
    {
        try {
            $response = Http::timeout((int) config('inference.timeout', 120))
                ->post($this->url().'/api/chat', $this->buildBody($request));
        } catch (\Throwable $e) {
            return $this->error($request->customId, $e->getMessage());
        }

        return $this->parsePoolResponse($response, $request);
    }

    public function poolRequest(Pool $pool, BatchRequestDto $request): void
    {
        $pool->as($request->customId)
            ->timeout((int) config('inference.timeout', 120))
            ->post($this->url().'/api/chat', $this->buildBody($request));
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
        $doneReason = $json['done_reason'] ?? 'stop';

        return new BatchResultDto(
            customId: $request->customId,
            succeeded: true,
            content: $json['message']['content'] ?? null,
            model: $json['model'] ?? $request->model ?? config('inference.model', 'llama3.2'),
            stopReason: $doneReason === 'length' ? 'max_tokens' : 'end_turn',
            inputTokens: $json['prompt_eval_count'] ?? 0,
            outputTokens: $json['eval_count'] ?? 0,
            error: null,
        );
    }

    private function buildBody(BatchRequestDto $request): array
    {
        return array_filter([
            'model' => $request->model ?? config('inference.model', 'llama3.2'),
            'messages' => $request->messages,
            'system' => $request->system,
            'stream' => false,
            'keep_alive' => config('inference.keep_alive', '5m'),
            'options' => ['num_predict' => $request->maxTokens],
        ], fn ($v) => $v !== null);
    }

    private function url(): string
    {
        return rtrim((string) config('inference.url', 'http://localhost:11434'), '/');
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
