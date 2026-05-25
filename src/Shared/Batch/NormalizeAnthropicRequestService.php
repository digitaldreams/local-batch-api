<?php

namespace BatchApi\Shared\Batch;

class NormalizeAnthropicRequestService
{
    /**
     * @param  array<int, array{custom_id: string, params: array{model?: string, max_tokens?: int, system?: string, messages: array<int, array{role: string, content: string|array<int, array{type: string, text?: string}>}>}}>  $requests
     * @return array<int, array{custom_id: string, model: string|null, max_tokens: int|null, system: string|null, messages: array<int, array{role: string, content: string}>}>
     */
    public function normalize(array $requests): array
    {
        return array_map(fn (array $req) => [
            'custom_id'  => $req['custom_id'],
            'model'      => $req['params']['model'] ?? null,
            'max_tokens' => $req['params']['max_tokens'] ?? null,
            'system'     => isset($req['params']['system'])
                ? $this->flattenContent($req['params']['system'])
                : null,
            'messages'   => array_map(
                fn (array $msg) => [
                    'role'    => $msg['role'],
                    'content' => $this->flattenContent($msg['content']),
                ],
                $req['params']['messages']
            ),
        ], $requests);
    }

    /**
     * Anthropic content is string OR array of content blocks.
     * Ollama only accepts string — extract text blocks and join.
     *
     * @param  string|array<int, array{type: string, text?: string}>  $content
     */
    private function flattenContent(string|array $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        return implode("\n", array_filter(
            array_map(fn (array $block) => $block['type'] === 'text' ? ($block['text'] ?? '') : null, $content)
        ));
    }
}
