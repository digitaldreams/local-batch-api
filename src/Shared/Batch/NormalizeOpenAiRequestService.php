<?php

namespace BatchApi\Shared\Batch;

class NormalizeOpenAiRequestService
{
    /**
     * @param  string  $jsonl  Raw JSONL content from uploaded file
     * @return array<int, array{custom_id: string, model: string|null, max_tokens: int|null, messages: array<int, array{role: string, content: string}>}>
     */
    public function normalize(string $jsonl): array
    {
        $rows = array_filter(array_map('trim', explode("\n", $jsonl)));

        return array_values(array_map(function (string $line): array {
            $row = json_decode($line, true);

            return [
                'custom_id'  => $row['custom_id'],
                'model'      => $row['body']['model'] ?? null,
                'max_tokens' => $row['body']['max_tokens'] ?? null,
                'messages'   => $row['body']['messages'],
            ];
        }, $rows));
    }
}
