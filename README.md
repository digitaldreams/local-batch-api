# local-batch-api

Drop-in replacement for the **Anthropic** and **OpenAI** batch APIs — self-hosted, running against local [Ollama](https://ollama.ai) or [LM Studio](https://lmstudio.ai). No API keys, no cloud costs, no rate limits.

---

## Requirements

- PHP 8.4+
- Laravel 13+
- Running [Ollama](https://ollama.ai/download) or [LM Studio](https://lmstudio.ai) instance
- A queue worker (`php artisan queue:work`)

---

## Installation

### Step 1 — Install the package

```bash
composer require digitaldreams/local-batch-api
```

### Step 2 — Run migrations

```bash
php artisan migrate
```

This creates two tables: `batches` and `batch_files`.

### Step 3 — Configure your inference backend

Add to your `.env`:

```env
# 'ollama' (default) or 'lmstudio'
INFERENCE_PROVIDER=ollama

# Base URL of your local inference server
INFERENCE_URL=http://localhost:11434   # Ollama default
# INFERENCE_URL=http://localhost:1234  # LM Studio default

# Default model (can be overridden per request)
INFERENCE_MODEL=llama3.2

# Seconds before a single request times out
INFERENCE_TIMEOUT=120

# Parallel requests per batch chunk — keep at 1 for CPU, raise to 3-5 for GPU
INFERENCE_CONCURRENCY=1
```

### Step 4 — Start a queue worker

Batch jobs run asynchronously. The worker must be running:

```bash
php artisan queue:work
```

---

## Two Ways to Use This Package

This package supports two independent usage patterns:

| | Event-based | REST API |
|---|---|---|
| **Who calls it** | Your own Laravel code | Any HTTP client (SDK, curl, external app) |
| **Auth** | Laravel's existing auth | Sanctum token (or your middleware) |
| **Routes needed** | No | Yes |
| **Best for** | Internal pipelines, jobs, commands | Replacing Anthropic/OpenAI SDK endpoints |

---

## Approach 1 — Event-based (Internal Usage)

Use this when your own Laravel application needs to submit and process batches. No HTTP routes required.

### Submitting an Anthropic-format batch

Fire a `SubmitAnthropicBatchEvent` event. The package listener picks it up and dispatches the processing job automatically.

```php
use BatchApi\Events\SubmitAnthropicBatchEvent;
use BatchApi\Data\Input\AnthropicBatchItemDto;

$items = [
    new AnthropicBatchItemDto(
        customId: 'req-1',
        maxTokens: 512,
        messages: [
            ['role' => 'user', 'content' => 'Summarise this article in one paragraph.'],
        ],
    ),
    new AnthropicBatchItemDto(
        customId: 'req-2',
        maxTokens: 256,
        messages: [
            ['role' => 'user', 'content' => 'What is the capital of France?'],
        ],
        system: 'You are a geography expert.',
    ),
];

event(new SubmitAnthropicBatchEvent($items));
```

### Submitting an OpenAI-format batch

The OpenAI flow requires a file ID. Upload first using the `BatchService`, then fire the event.

```php
use BatchApi\BatchService;
use BatchApi\Events\SubmitOpenAiBatchEvent;
use BatchApi\Data\Input\OpenAiBatchItemDto;

$service = app(BatchService::class);

// Build items from raw JSONL or manually
$items = [
    new OpenAiBatchItemDto(
        customId: 'req-1',
        messages: [['role' => 'user', 'content' => 'Hello']],
        maxTokens: 512,
    ),
];

// Create a file record (mirrors OpenAI's file upload step)
$file = $service->uploadFile(
    collect($items)->map(fn ($item) => json_encode([
        'custom_id' => $item->customId,
        'method' => 'POST',
        'url' => '/v1/chat/completions',
        'body' => ['messages' => $item->messages, 'max_tokens' => $item->maxTokens],
    ]))->implode("\n")
);

event(new SubmitOpenAiBatchEvent($file->id, $items));
```

### Listening for results

Listen to `BatchCompletedEvent` to act on results when processing finishes:

```php
// app/Listeners/HandleBatchCompletedListener.php

use BatchApi\Events\BatchCompletedEvent;
use BatchApi\Data\BatchResultDto;

class HandleBatchCompletedListener
{
    public function handle(BatchCompletedEvent $event): void
    {
        $batch = $event->batch;

        foreach ($event->results as $result) {
            /** @var BatchResultDto $result */
            if ($result->succeeded) {
                // $result->customId   — matches your request's custom_id
                // $result->content    — the model's response text
                // $result->model      — model used
                // $result->inputTokens / $result->outputTokens
            } else {
                // $result->error — failure message
            }
        }
    }
}
```

Register it in `AppServiceProvider::boot()`:

```php
// app/Providers/AppServiceProvider.php

use BatchApi\Events\BatchCompletedEvent;
use App\Listeners\HandleBatchCompletedListener;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(BatchCompletedEvent::class, HandleBatchCompletedListener::class);
}
```

### All available events

| Event | Properties | Fired when |
|-------|-----------|------------|
| `BatchCreatedEvent` | `$batch`, `$items`, `$provider` | Batch record saved, job dispatched |
| `BatchProcessingEvent` | `$batch` | Queue worker picks up the job |
| `BatchItemStartedEvent` | `$batch`, `$dto` | Single request about to fire |
| `BatchItemCompletedEvent` | `$batch`, `$result` | Single request finished |
| `BatchCompletedEvent` | `$batch`, `$results` | All requests done |
| `BatchFailedEvent` | `$batch`, `$exception` | Job threw an unrecoverable error |
| `BatchCancelledEvent` | `$batch` | Batch cancelled |

---

## Approach 2 — REST API (External HTTP Clients)

Use this when you want to **point an existing Anthropic or OpenAI SDK** at your local server instead of the cloud. The API surface is identical to the real APIs.

### Step 1 — Register routes with authentication

Do **not** set `BATCH_API_EXPOSE_ROUTES=true`. Instead, register routes manually inside a protected middleware group so you control authentication.

Install Sanctum if you haven't already:

```bash
composer require laravel/sanctum
php artisan install:api
```

In your `routes/api.php` (or a service provider), wrap `BatchApi::routes()` with Sanctum middleware:

```php
use BatchApi\Facades\BatchApi;

Route::middleware('auth:sanctum')->group(function () {
    BatchApi::routes();
});
```

This registers all 11 endpoints, each requiring a valid Sanctum token.

> **Note:** `BatchApi::routes()` also applies the `api` middleware internally. Wrapping it with `auth:sanctum` stacks both, so your routes have `api` + `auth:sanctum`.

### Step 2 — Issue a token

```php
// In a controller or seeder
$token = $user->createToken('batch-api-client')->plainTextToken;
// Pass this token to the HTTP client
```

### Step 3 — Call the API

All requests need the token in the `Authorization` header:

```
Authorization: Bearer <token>
```

---

### Anthropic Batch API — Step by Step

#### 1. Submit a batch

```http
POST /api/anthropic/v1/messages/batches
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "requests": [
    {
      "custom_id": "req-1",
      "params": {
        "model": "llama3.2",
        "max_tokens": 512,
        "messages": [
          { "role": "user", "content": "Say hello in one sentence." }
        ]
      }
    },
    {
      "custom_id": "req-2",
      "params": {
        "model": "llama3.2",
        "max_tokens": 512,
        "system": "You are a pirate. Always respond like a pirate.",
        "messages": [
          { "role": "user", "content": "What is the capital of France?" }
        ]
      }
    }
  ]
}
```

Response `202 Accepted`:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "type": "message_batch",
  "processing_status": "in_progress",
  "request_counts": { "processing": 2, "succeeded": 0, "errored": 0, "canceled": 0, "expired": 0 },
  "created_at": "2026-05-25T10:00:00+00:00",
  "expires_at": "2026-05-26T10:00:00+00:00",
  "ended_at": null,
  "cancel_initiated_at": null,
  "results_url": null
}
```

#### 2. Poll until done

```http
GET /api/anthropic/v1/messages/batches/{id}
Authorization: Bearer <token>
```

Keep polling until `processing_status` is `"ended"`.

#### 3. Fetch results (NDJSON)

```http
GET /api/anthropic/v1/messages/batches/{id}/results
Accept: application/x-ndjson
Authorization: Bearer <token>
```

Returns `204 No Content` if still processing. When ready, streams one JSON object per line:

```jsonl
{"custom_id":"req-1","result":{"type":"succeeded","message":{"id":"msg_abc","type":"message","role":"assistant","model":"llama3.2","content":[{"type":"text","text":"Hello! Great to meet you."}],"stop_reason":"end_turn","usage":{"input_tokens":12,"output_tokens":10}}}}
{"custom_id":"req-2","result":{"type":"errored","error":{"type":"server_error","message":"Ollama timeout"}}}
```

#### Other Anthropic endpoints

```http
GET  /api/anthropic/v1/messages/batches              # list (supports ?limit=&before_id=&after_id=)
POST /api/anthropic/v1/messages/batches/{id}/cancel  # cancel
```

---

### OpenAI Batch API — Step by Step

#### 1. Upload a JSONL file

Create a `.jsonl` file (one request per line):

```jsonl
{"custom_id":"req-1","method":"POST","url":"/v1/chat/completions","body":{"model":"llama3.2","messages":[{"role":"user","content":"Hello"}],"max_tokens":512}}
{"custom_id":"req-2","method":"POST","url":"/v1/chat/completions","body":{"model":"llama3.2","messages":[{"role":"user","content":"What is 2+2?"}],"max_tokens":256}}
```

Upload it:

```http
POST /api/openai/v1/files
Content-Type: multipart/form-data
Authorization: Bearer <token>

file=@requests.jsonl
purpose=batch
```

Response `201 Created`:

```json
{
  "id": "file-abc123",
  "object": "file",
  "purpose": "batch",
  "created_at": 1716631200
}
```

#### 2. Submit the batch

```http
POST /api/openai/v1/batches
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "input_file_id": "file-abc123",
  "endpoint": "/v1/chat/completions",
  "completion_window": "24h"
}
```

Response `201 Created`:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440001",
  "object": "batch",
  "status": "validating",
  "input_file_id": "file-abc123",
  "output_file_id": null,
  "request_counts": { "total": 2, "completed": 0, "failed": 0 }
}
```

#### 3. Poll until completed

```http
GET /api/openai/v1/batches/{id}
Authorization: Bearer <token>
```

Poll until `status` is `"completed"`. Note the `output_file_id` in the response.

#### 4. Download results

```http
GET /api/openai/v1/files/{output_file_id}/content
Authorization: Bearer <token>
```

Returns JSONL, one result per line:

```jsonl
{"id":"batch_req_abc","custom_id":"req-1","response":{"status_code":200,"body":{"id":"chatcmpl-123","object":"chat.completion","model":"llama3.2","choices":[{"index":0,"message":{"role":"assistant","content":"Hello! How can I help?"},"finish_reason":"stop"}],"usage":{"prompt_tokens":10,"completion_tokens":8,"total_tokens":18}}},"error":null}
```

#### Other OpenAI endpoints

```http
GET  /api/openai/v1/batches                  # list (supports ?limit=&after=)
POST /api/openai/v1/batches/{id}/cancel      # cancel
```

---

### Pointing an existing SDK at this server

**Python (Anthropic SDK):**

```python
import anthropic

client = anthropic.Anthropic(
    api_key="any-value",           # required by SDK but not validated here
    base_url="http://localhost:8000/api/anthropic",
    default_headers={"Authorization": "Bearer <token>"},
)
```

**Python (OpenAI SDK):**

```python
from openai import OpenAI

client = OpenAI(
    api_key="any-value",
    base_url="http://localhost:8000/api/openai",
    default_headers={"Authorization": "Bearer <token>"},
)
```

---

## Batch Status Lifecycle

```
pending → processing → completed
                     → failed
       → cancelling  → cancelled
```

| Internal | Anthropic `processing_status` | OpenAI `status` |
|----------|-------------------------------|-----------------|
| `pending` | `in_progress` | `validating` |
| `processing` | `in_progress` | `in_progress` |
| `completed` | `ended` | `completed` |
| `failed` | `ended` | `failed` |
| `cancelling` | `canceling` | `cancelling` |
| `cancelled` | `ended` | `cancelled` |

Batches expire after 24 hours.

---

## Switching to LM Studio

1. Open LM Studio → start the local server (default port `1234`)
2. Load a model
3. Update `.env`:

```env
INFERENCE_PROVIDER=lmstudio
INFERENCE_URL=http://localhost:1234
INFERENCE_MODEL=your-model-name
```

No other changes needed.

---

## Concurrency Tuning

`INFERENCE_CONCURRENCY` controls parallel requests per batch chunk.

| Hardware | Value |
|----------|-------|
| CPU-only | `1` |
| GPU with spare VRAM | `3`–`5` |

---

## Postman Collection

Import `Local-Batch-API.postman_collection.json`. Set the `baseUrl` variable to your server URL. The collection auto-saves batch IDs and file IDs between requests so you can run folders top-to-bottom without manually copying values.

---

## Troubleshooting

**Batches stay `pending` forever** — Queue worker not running. Run `php artisan queue:work`.

**`Ollama timeout` in results** — Model is slow or `INFERENCE_TIMEOUT` too low. Raise to `300`.

**Routes return 404** — Routes not registered. Either set `BATCH_API_EXPOSE_ROUTES=true` (no auth) or call `BatchApi::routes()` manually in a middleware group.

**401 Unauthorized on API routes** — Sanctum token missing or invalid. Pass `Authorization: Bearer <token>` header.

**`cannot chdir` git error in submodule** — Run `git submodule update --init` in the parent repo.

---

## License

MIT
