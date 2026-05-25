<?php

namespace BatchApi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BatchApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ollama.php', 'ollama');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Shared/database/migrations');

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/routes/api.php');
    }
}
