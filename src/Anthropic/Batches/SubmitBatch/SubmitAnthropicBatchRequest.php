<?php

namespace BatchApi\Anthropic\Batches\SubmitBatch;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnthropicBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requests'                                    => ['required', 'array', 'min:1'],
            // Real Anthropic: ^[a-zA-Z0-9_-]{1,64}$
            'requests.*.custom_id'                        => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'requests.*.params'                           => ['required', 'array'],
            'requests.*.params.model'                     => ['sometimes', 'string'],
            // Real Anthropic: max_tokens is required, min 1
            'requests.*.params.max_tokens'                => ['required', 'integer', 'min:1'],
            // system: string OR array of text content blocks
            'requests.*.params.system'                    => ['sometimes'],
            'requests.*.params.system.*'                  => ['sometimes', 'array'],
            'requests.*.params.system.*.type'             => ['sometimes', 'string', 'in:text'],
            'requests.*.params.system.*.text'             => ['sometimes', 'string'],
            'requests.*.params.messages'                  => ['required', 'array', 'min:1'],
            'requests.*.params.messages.*.role'           => ['required', 'string', 'in:user,assistant,system'],
            // content: string OR array of content blocks
            'requests.*.params.messages.*.content'        => ['required'],
            'requests.*.params.messages.*.content.*'      => ['sometimes', 'array'],
            'requests.*.params.messages.*.content.*.type' => ['sometimes', 'string', 'in:text,image,tool_use,tool_result'],
            'requests.*.params.messages.*.content.*.text' => ['sometimes', 'string'],
        ];
    }
}
