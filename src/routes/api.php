<?php

use BatchApi\Anthropic\Batches\Cancel\CancelAnthropicBatchController;
use BatchApi\Anthropic\Batches\GetBatch\GetAnthropicBatchController;
use BatchApi\Anthropic\Batches\GetResults\GetAnthropicBatchResultsController;
use BatchApi\Anthropic\Batches\List\ListAnthropicBatchesController;
use BatchApi\Anthropic\Batches\SubmitBatch\SubmitAnthropicBatchController;
use BatchApi\OpenAi\Batches\Cancel\CancelOpenAiBatchController;
use BatchApi\OpenAi\Batches\GetBatch\GetOpenAiBatchController;
use BatchApi\OpenAi\Batches\List\ListOpenAiBatchesController;
use BatchApi\OpenAi\Batches\SubmitBatch\SubmitOpenAiBatchController;
use BatchApi\OpenAi\Files\GetFileContentController;
use BatchApi\OpenAi\Files\UploadFileController;
use Illuminate\Support\Facades\Route;

Route::prefix('anthropic/v1')->group(function (): void {
    Route::post('messages/batches', [SubmitAnthropicBatchController::class, 'store']);
    Route::get('messages/batches', [ListAnthropicBatchesController::class, 'index']);
    Route::get('messages/batches/{batch}', [GetAnthropicBatchController::class, 'show']);
    Route::get('messages/batches/{batch}/results', [GetAnthropicBatchResultsController::class, 'show']);
    Route::post('messages/batches/{batch}/cancel', [CancelAnthropicBatchController::class, 'store']);
});

Route::prefix('openai/v1')->group(function (): void {
    Route::post('files', [UploadFileController::class, 'store']);
    Route::get('files/{batchFile}/content', [GetFileContentController::class, 'show']);
    Route::post('batches', [SubmitOpenAiBatchController::class, 'store']);
    Route::get('batches', [ListOpenAiBatchesController::class, 'index']);
    Route::get('batches/{batch}', [GetOpenAiBatchController::class, 'show']);
    Route::post('batches/{batch}/cancel', [CancelOpenAiBatchController::class, 'store']);
});
