<?php

namespace BatchApi\Shared\Batch\Models;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): Factory
    {
        return BatchFactory::new();
    }

    protected $table = 'batches';

    protected $fillable = [
        'provider_format',
        'status',
        'payload',
        'raw_response',
        'input_file_id',
        'output_file_id',
        'request_count',
        'succeeded_count',
        'errored_count',
        'expires_at',
        'completed_at',
        'in_progress_at',
        'cancel_initiated_at',
    ];

    protected $casts = [
        'status' => BatchStatus::class,
        'payload' => 'array',
        'raw_response' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'cancel_initiated_at' => 'datetime',
    ];
}
