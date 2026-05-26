<?php

namespace BatchApi\Facades;

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
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');
    }
}
