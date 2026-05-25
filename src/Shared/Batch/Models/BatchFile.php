<?php

namespace BatchApi\Shared\Batch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchFile extends Model
{
    use HasFactory;

    protected $table = 'batch_files';

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

    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\BatchFileFactory::new();
    }
}
