<?php

namespace BatchApi\Inference;

class InferenceAdapterFactory
{
    public static function make(): InferenceAdapterInterface
    {
        return match (config('inference.provider', 'ollama')) {
            'lmstudio' => new LmStudioAdapter,
            default => new OllamaAdapter,
        };
    }
}
