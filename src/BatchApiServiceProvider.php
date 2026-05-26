<?php

namespace BatchApi;

use BatchApi\Events\CancelBatch;
use BatchApi\Events\SubmitAnthropicBatch;
use BatchApi\Events\SubmitOpenAiBatch;
use BatchApi\Facades\BatchApi;
use BatchApi\Listeners\HandleCancelBatch;
use BatchApi\Listeners\HandleSubmitAnthropicBatch;
use BatchApi\Listeners\HandleSubmitOpenAiBatch;
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
        $this->loadMigrationsFrom(__DIR__.'/Shared/database/migrations');

        Event::listen(SubmitAnthropicBatch::class, HandleSubmitAnthropicBatch::class);
        Event::listen(SubmitOpenAiBatch::class, HandleSubmitOpenAiBatch::class);
        Event::listen(CancelBatch::class, HandleCancelBatch::class);

        if (config('inference.expose_routes', false)) {
            BatchApi::routes();
        }
    }
}
