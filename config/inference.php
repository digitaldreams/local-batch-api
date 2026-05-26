<?php

return [
    'provider'      => env('INFERENCE_PROVIDER', 'ollama'),  // 'ollama' | 'lmstudio'
    'url'           => env('INFERENCE_URL', 'http://localhost:11434'),
    'model'         => env('INFERENCE_MODEL', 'llama3.2'),
    'keep_alive'    => env('INFERENCE_KEEP_ALIVE', '5m'),    // Ollama only
    'timeout'       => env('INFERENCE_TIMEOUT', 120),
    'concurrency'   => env('INFERENCE_CONCURRENCY', 1),
    'expose_routes' => env('BATCH_API_EXPOSE_ROUTES', false),
];
