<?php

namespace BatchApi\Data\Input;

use Illuminate\Validation\ValidationException;

final class AnthropicBatchItemDto
{
    public function __construct(
        public readonly string $customId,
        public readonly int $maxTokens,
        public readonly array $messages,
        public readonly ?string $model = null,
        public readonly mixed $system = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $validated = validator($data, [
            'custom_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'params' => ['required', 'array'],
            'params.max_tokens' => ['required', 'integer', 'min:1'],
            'params.model' => ['sometimes', 'string'],
            'params.system' => ['sometimes'],
            'params.messages' => ['required', 'array', 'min:1'],
            'params.messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'params.messages.*.content' => ['required'],
        ])->validate();

        return new self(
            customId: $validated['custom_id'],
            maxTokens: $validated['params']['max_tokens'],
            messages: $validated['params']['messages'],
            model: $validated['params']['model'] ?? null,
            system: $validated['params']['system'] ?? null,
        );
    }

    /**
     * Validates all items, collects ALL errors across all indexes,
     * throws a single ValidationException.
     *
     * @return self[]
     */
    public static function fromCollection(array $rawItems): array
    {
        $errors = [];
        $dtos = [];

        foreach ($rawItems as $index => $rawItem) {
            try {
                $dtos[] = self::fromArray($rawItem);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $errors["requests.{$index}.{$field}"] = $messages;
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $dtos;
    }
}
