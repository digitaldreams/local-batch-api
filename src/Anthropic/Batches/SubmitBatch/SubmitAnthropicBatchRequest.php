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
            'requests'                        => ['required', 'array', 'min:1'],
            'requests.*.custom_id'            => ['required', 'string'],
            'requests.*.params'               => ['required', 'array'],
            'requests.*.params.model'         => ['sometimes', 'string'],
            'requests.*.params.max_tokens'    => ['sometimes', 'integer', 'min:1'],
            'requests.*.params.messages'      => ['required', 'array', 'min:1'],
            'requests.*.params.messages.*.role'    => ['required', 'string', 'in:user,assistant,system'],
            'requests.*.params.messages.*.content' => ['required', 'string'],
        ];
    }
}
