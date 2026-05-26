<?php

namespace BatchApi\Data\Input;

use Illuminate\Validation\ValidationException;

final class OpenAiBatchItemDto
{
    public function __construct(
        public readonly string $customId,
        public readonly array $messages,
        public readonly ?int $maxTokens = null,
        public readonly ?string $model = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $validated = validator($data, [
            'custom_id' => ['required', 'string'],
            'body' => ['required', 'array'],
            'body.messages' => ['required', 'array', 'min:1'],
            'body.messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'body.messages.*.content' => ['required'],
            'body.max_tokens' => ['sometimes', 'integer', 'min:1'],
            'body.model' => ['sometimes', 'string'],
        ])->validate();

        return new self(
            customId: $validated['custom_id'],
            messages: $validated['body']['messages'],
            maxTokens: $validated['body']['max_tokens'] ?? null,
            model: $validated['body']['model'] ?? null,
        );
    }

    /**
     * Parse a JSONL string into validated DTOs, collecting all errors.
     *
     * @return self[]
     */
    public static function fromJsonl(string $jsonl): array
    {
        $errors = [];
        $dtos = [];
        $lines = array_values(array_filter(array_map('trim', explode("\n", $jsonl))));

        foreach ($lines as $index => $line) {
            try {
                $dtos[] = self::fromArray(json_decode($line, true) ?? []);
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
