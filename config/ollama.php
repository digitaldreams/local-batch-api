<?php

return [
    'url'        => env('OLLAMA_URL', 'http://localhost:11434'),
    'model'      => env('OLLAMA_MODEL', 'llama3.2'),
    'keep_alive' => env('OLLAMA_KEEP_ALIVE', '5m'),
];
