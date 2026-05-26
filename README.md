# local-batch-api

Drop-in replacement for the **Anthropic** and **OpenAI** batch APIs — self-hosted, running against local [Ollama](https://ollama.ai) or [LM Studio](https://lmstudio.ai). Point your existing SDK code at this server instead of the real APIs. No API keys, no cloud costs, no rate limits.

---

## How It Works

1. You POST a batch of prompts (same format as Anthropic/OpenAI).
2. The package stores the batch in your database and dispatches a queued job.
3. The job calls Ollama or LM Studio sequentially (or concurrently) for each request.
4. You poll for status, then fetch results in the same format the real API would return.

---

## Requirements

- PHP 8.4+
- Laravel 13+
- A running [Ollama](https://ollama.ai/download) instance **or** LM Studio with its local server enabled
- A queue worker (`php artisan queue:work`)

---

## Installation

```bash
composer require digitaldreams/local-batch-api
```

Laravel auto-discovers the service provider. Run migrations:

```bash
php artisan migrate
```

---

## Configuration

Publish the config (optional — env vars work without publishing):

```bash
php artisan vendor:publish --tag=inference-config
```

Key `.env` variables:

```env
# Which backend to use: 'ollama' (default) or 'lmstudio'
INFERENCE_PROVIDER=ollama

# Base URL of the inference server
INFERENCE_URL=http://localhost:11434   # Ollama default
# INFERENCE_URL=http://localhost:1234  # LM Studio default

# Default model (overridable per request)
INFERENCE_MODEL=llama3.2

# Seconds before a single request is considered timed out
INFERENCE_TIMEOUT=120

# How many requests to fire in parallel per batch chunk
# CPU-only: keep at 1. GPU with VRAM headroom: raise to 3–5.
INFERENCE_CONCURRENCY=1

# Set to true to auto-register the HTTP routes
BATCH_API_EXPOSE_ROUTES=true
```

> **Route registration**: routes are off by default. Either set `BATCH_API_EXPOSE_ROUTES=true` or call `BatchApi::routes()` in your `RouteServiceProvider`.

---

## Quickstart

### 1. Start Ollama and pull a model

```bash
ollama pull llama3.2
ollama serve          # starts on http://localhost:11434
```

### 2. Start a queue worker

```bash
php artisan queue:work
```

### 3. Start your Laravel app

```bash
php artisan serve     # http://localhost:8000
```

---

## API Reference

Both APIs share the same lifecycle:

```
Submit batch → Poll status → Fetch results
```

---

### Anthropic Batch API

Mirrors the [Anthropic Message Batches API](https://docs.anthropic.com/en/api/creating-message-batches).

#### Submit a batch

```http
POST /api/anthropic/v1/messages/batches
Content-Type: application/json
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

#### Poll status

```http
GET /api/anthropic/v1/messages/batches/{id}
```

Keep polling until `processing_status` is `"ended"`.

#### Get results (NDJSON stream)

```http
GET /api/anthropic/v1/messages/batches/{id}/results
Accept: application/x-ndjson
```

Returns `204` if not ready yet. When ready, streams one JSON object per line:

```jsonl
{"custom_id":"req-1","result":{"type":"succeeded","message":{"id":"msg_abc","type":"message","role":"assistant","model":"llama3.2","content":[{"type":"text","text":"Hello! Great to meet you."}],"stop_reason":"end_turn","usage":{"input_tokens":12,"output_tokens":10}}}}
{"custom_id":"req-2","result":{"type":"errored","error":{"type":"server_error","message":"Ollama timeout"}}}
```

#### List batches

```http
GET /api/anthropic/v1/messages/batches?limit=20
GET /api/anthropic/v1/messages/batches?limit=20&before_id={id}
GET /api/anthropic/v1/messages/batches?limit=20&after_id={id}
```

#### Cancel a batch

```http
POST /api/anthropic/v1/messages/batches/{id}/cancel
```

Returns `processing_status: "canceling"` immediately. Returns `422` if batch already ended.

---

### OpenAI Batch API

Mirrors the [OpenAI Batch API](https://platform.openai.com/docs/guides/batch). Uses a two-step upload-then-submit flow.

#### Step 1 — Upload a JSONL file

Create a `.jsonl` file (one request per line):

```jsonl
{"custom_id":"req-1","method":"POST","url":"/v1/chat/completions","body":{"model":"llama3.2","messages":[{"role":"user","content":"Hello"}],"max_tokens":512}}
{"custom_id":"req-2","method":"POST","url":"/v1/chat/completions","body":{"model":"llama3.2","messages":[{"role":"user","content":"What is 2+2?"}],"max_tokens":256}}
```

Upload it:

```http
POST /api/openai/v1/files
Content-Type: multipart/form-data

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

#### Step 2 — Submit the batch

```http
POST /api/openai/v1/batches
Content-Type: application/json
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

#### Poll status

```http
GET /api/openai/v1/batches/{id}
```

Poll until `status` is `"completed"`. Note the `output_file_id` in the response.

#### Download results

```http
GET /api/openai/v1/files/{output_file_id}/content
```

Returns JSONL, one result per line:

```jsonl
{"id":"batch_req_abc","custom_id":"req-1","response":{"status_code":200,"body":{"id":"chatcmpl-123","object":"chat.completion","model":"llama3.2","choices":[{"index":0,"message":{"role":"assistant","content":"Hello! How can I help?"},"finish_reason":"stop"}],"usage":{"prompt_tokens":10,"completion_tokens":8,"total_tokens":18}}},"error":null}
```

#### List batches

```http
GET /api/openai/v1/batches?limit=20
GET /api/openai/v1/batches?limit=20&after={id}
```

#### Cancel a batch

```http
POST /api/openai/v1/batches/{id}/cancel
```

---

## Batch Status Lifecycle

```
pending → processing → completed
                     → failed
       → cancelling  → cancelled
```

| Internal status | Anthropic `processing_status` | OpenAI `status`  |
|-----------------|-------------------------------|------------------|
| `pending`       | `in_progress`                 | `validating`     |
| `processing`    | `in_progress`                 | `in_progress`    |
| `completed`     | `ended`                       | `completed`      |
| `failed`        | `ended`                       | `failed`         |
| `cancelling`    | `canceling`                   | `cancelling`     |
| `cancelled`     | `ended`                       | `cancelled`      |

Batches expire after 24 hours.

---

## Events

Listen to these Laravel events to hook into the batch lifecycle:

| Event | Fired when |
|-------|-----------|
| `BatchApi\Events\BatchCreated` | Batch submitted |
| `BatchApi\Events\BatchProcessing` | Job picks up the batch |
| `BatchApi\Events\BatchItemStarted` | Single request starts |
| `BatchApi\Events\BatchItemCompleted` | Single request finishes |
| `BatchApi\Events\BatchCompleted` | All requests done |
| `BatchApi\Events\BatchFailed` | Job threw an exception |
| `BatchApi\Events\BatchCancelled` | Batch cancelled |

Example listener:

```php
use BatchApi\Events\BatchCompleted;

class NotifyWhenBatchDone
{
    public function handle(BatchCompleted $event): void
    {
        $batch = $event->batch;
        $results = $event->results; // BatchResultDto[]

        foreach ($results as $result) {
            if ($result->succeeded) {
                // $result->content, $result->inputTokens, $result->outputTokens
            } else {
                // $result->error
            }
        }
    }
}
```

Register it in `EventServiceProvider`:

```php
protected $listen = [
    \BatchApi\Events\BatchCompleted::class => [
        \App\Listeners\NotifyWhenBatchDone::class,
    ],
];
```

---

## Concurrency Tuning

The `INFERENCE_CONCURRENCY` env var controls how many Ollama/LM Studio requests fire in parallel per batch.

| Hardware | Recommended value |
|----------|------------------|
| CPU-only (no discrete GPU) | `1` |
| GPU with spare VRAM | `3`–`5` |

Setting concurrency > 1 uses Laravel's `Http::pool()` to fire requests simultaneously.

---

## Using the Postman Collection

Import `Ollama-Batch-API.postman_collection.json` into Postman. Set the `baseUrl` variable to `http://localhost:8000`.

The collection auto-saves batch IDs and file IDs between requests so you can run the folder top-to-bottom without manually copying values.

---

## Switching to LM Studio

1. Open LM Studio → start the local server (default port `1234`)
2. Load a model in LM Studio
3. Update `.env`:

```env
INFERENCE_PROVIDER=lmstudio
INFERENCE_URL=http://localhost:1234
INFERENCE_MODEL=your-model-name
```

No other changes needed. The LM Studio adapter uses the OpenAI-compatible `/v1/chat/completions` endpoint.

---

## Troubleshooting

**Batches stay `pending` forever**
Queue worker is not running. Start it: `php artisan queue:work`

**`Ollama timeout` errors in results**
Model is slow or `INFERENCE_TIMEOUT` is too low. Increase it: `INFERENCE_TIMEOUT=300`

**Routes return 404**
`BATCH_API_EXPOSE_ROUTES` is not `true`. Add it to `.env` and clear config cache: `php artisan config:clear`

**`ollama: command not found` / connection refused**
Ollama is not running. Run `ollama serve` in a separate terminal.

---

## License

MIT
