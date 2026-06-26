<?php

return [
    'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    'model' => env('OLLAMA_MODEL', 'llama3.2'),
    'keep_alive' => env('OLLAMA_KEEP_ALIVE', '5m'),
    'timeout' => env('OLLAMA_TIMEOUT', 120),
    // How many Ollama requests fire concurrently per pool chunk.
    // CPU-only hosts (Intel Xe, no discrete GPU): set to 1.
    // GPU hosts with VRAM headroom: raise to 3–5.
    'concurrency' => env('OLLAMA_CONCURRENCY', 1),
];
