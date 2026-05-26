<?php

namespace BatchApi\Data;

use Illuminate\Support\Str;

final class BatchResultDto
{
    public function __construct(
        public readonly string $customId,
        public readonly bool $succeeded,
        public readonly ?string $content,
        public readonly ?string $model,
        public readonly ?string $stopReason,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly ?string $error,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customId: $data['custom_id'],
            succeeded: $data['succeeded'],
            content: $data['content'] ?? null,
            model: $data['model'] ?? null,
            stopReason: $data['stop_reason'] ?? null,
            inputTokens: $data['input_tokens'] ?? 0,
            outputTokens: $data['output_tokens'] ?? 0,
            error: $data['error'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'custom_id' => $this->customId,
            'succeeded' => $this->succeeded,
            'content' => $this->content,
            'model' => $this->model,
            'stop_reason' => $this->stopReason,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'error' => $this->error,
        ];
    }

    public function toAnthropicNdjson(): array
    {
        if (! $this->succeeded) {
            return [
                'custom_id' => $this->customId,
                'result' => [
                    'type' => 'errored',
                    'error' => [
                        'type' => 'server_error',
                        'message' => $this->error,
                    ],
                ],
            ];
        }

        return [
            'custom_id' => $this->customId,
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'id' => 'msg_'.Str::replace('-', '', Str::uuid()),
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => $this->model ?? 'unknown',
                    'content' => [['type' => 'text', 'text' => $this->content]],
                    'stop_reason' => $this->stopReason ?? 'end_turn',
                    'stop_sequence' => null,
                    'usage' => [
                        'input_tokens' => $this->inputTokens,
                        'output_tokens' => $this->outputTokens,
                    ],
                ],
            ],
        ];
    }

    public function toOpenAiJsonl(): array
    {
        $resultId = 'batch_req_'.Str::replace('-', '', substr((string) Str::uuid(), 0, 16));

        if (! $this->succeeded) {
            return [
                'id' => $resultId,
                'custom_id' => $this->customId,
                'response' => null,
                'error' => ['code' => 'server_error', 'message' => $this->error],
            ];
        }

        return [
            'id' => $resultId,
            'custom_id' => $this->customId,
            'response' => [
                'status_code' => 200,
                'request_id' => 'req_'.Str::replace('-', '', substr((string) Str::uuid(), 0, 16)),
                'body' => [
                    'id' => 'chatcmpl-'.Str::replace('-', '', substr((string) Str::uuid(), 0, 16)),
                    'object' => 'chat.completion',
                    'model' => $this->model ?? 'unknown',
                    'choices' => [
                        [
                            'index' => 0,
                            'message' => ['role' => 'assistant', 'content' => $this->content],
                            'finish_reason' => $this->stopReason === 'max_tokens' ? 'length' : 'stop',
                        ],
                    ],
                    'usage' => [
                        'prompt_tokens' => $this->inputTokens,
                        'completion_tokens' => $this->outputTokens,
                        'total_tokens' => $this->inputTokens + $this->outputTokens,
                    ],
                ],
            ],
            'error' => null,
        ];
    }
}
