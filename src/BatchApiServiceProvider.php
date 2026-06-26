<?php

namespace BatchApi;

use BatchApi\Events\CancelBatchEvent;
use BatchApi\Events\SubmitAnthropicBatchEvent;
use BatchApi\Events\SubmitOpenAiBatchEvent;
use BatchApi\Facades\BatchApi;
use BatchApi\Listeners\HandleCancelBatchListener;
use BatchApi\Listeners\HandleSubmitAnthropicBatchListener;
use BatchApi\Listeners\HandleSubmitOpenAiBatchListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BatchApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inference.php', 'inference');
        $this->mergeConfigFrom(__DIR__.'/../config/ollama.php', 'ollama');

        $this->app->singleton(BatchService::class);
        $this->app->alias(BatchService::class, 'batch-api');
    }

    public function boot(): void
    {
        // Inference-only by default: skip migrations, batch listeners and routes unless
        // persistence is enabled. Lets a host consume InferenceAdapterFactory without the
        // batches table colliding with its own schema.
        if (! config('inference.persistence', false)) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Event::listen(SubmitAnthropicBatchEvent::class, HandleSubmitAnthropicBatchListener::class);
        Event::listen(SubmitOpenAiBatchEvent::class, HandleSubmitOpenAiBatchListener::class);
        Event::listen(CancelBatchEvent::class, HandleCancelBatchListener::class);

        if (config('inference.expose_routes', false)) {
            BatchApi::routes();
        }
    }
}
