<?php

namespace BatchApi\Facades;

use BatchApi\Middleware\VerifyBatchApiSecret;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;

class BatchApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'batch-api';
    }

    public static function routes(): void
    {
        Route::middleware(['api', VerifyBatchApiSecret::class])
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');
    }
}
