<?php

namespace BatchApi\Shared\Batch\Models;

use BatchApi\Database\Factories\BatchFileFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchFile extends Model
{
    use HasFactory;

    protected $table = 'local_batch_api_batch_files';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'purpose',
        'content',
        'parsed_rows',
    ];

    protected $casts = [
        'parsed_rows' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return BatchFileFactory::new();
    }
}
