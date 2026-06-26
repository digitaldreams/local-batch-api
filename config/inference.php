<?php

return [
    'provider' => env('INFERENCE_PROVIDER', 'ollama'),  // 'ollama' | 'lmstudio'
    'url' => env('INFERENCE_URL', 'http://localhost:11434'),
    'model' => env('INFERENCE_MODEL', 'llama3.2'),
    'keep_alive' => env('INFERENCE_KEEP_ALIVE', '5m'),    // Ollama only
    'timeout' => env('INFERENCE_TIMEOUT', 120),
    'job_timeout' => env('INFERENCE_JOB_TIMEOUT', 600), // ProcessBatchJob queue timeout; raise for slow CPU boxes
    'concurrency' => env('INFERENCE_CONCURRENCY', 1),
    // Persistence off = inference-only: no migrations, no batch listeners, no routes.
    // Host using only InferenceAdapterFactory keeps this false to avoid the batches table.
    'persistence' => env('BATCH_API_PERSISTENCE', false),
    'expose_routes' => env('BATCH_API_EXPOSE_ROUTES', false), // requires persistence
    // Secret guarding exposed routes. When set, requests must send it in the
    // X-Batch-Api-Key header (or Authorization: Bearer). Null = routes open (local dev).
    'api_secret' => env('BATCH_API_SECRET'),
];
