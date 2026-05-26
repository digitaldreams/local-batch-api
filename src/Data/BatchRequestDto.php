<?php

namespace BatchApi\Data;

final class BatchRequestDto
{
    public function __construct(
        public readonly string $customId,
        public readonly array $messages,
        public readonly int $maxTokens,
        public readonly ?string $model = null,
        public readonly ?string $system = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customId: $data['custom_id'],
            messages: $data['messages'],
            maxTokens: $data['max_tokens'],
            model: $data['model'] ?? null,
            system: $data['system'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'custom_id' => $this->customId,
            'messages' => $this->messages,
            'max_tokens' => $this->maxTokens,
            'model' => $this->model,
            'system' => $this->system,
        ];
    }
}
