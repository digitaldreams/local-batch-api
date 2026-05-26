<?php

namespace BatchApi\Database\Factories;

use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BatchFile>
 */
class BatchFileFactory extends Factory
{
    protected $model = BatchFile::class;

    public function definition(): array
    {
        return [
            'id' => 'file-'.Str::uuid(),
            'purpose' => 'batch',
            'content' => '{"custom_id":"req-1","method":"POST","url":"/v1/chat/completions","body":{"model":"llama3.2","messages":[{"role":"user","content":"Hello"}],"max_tokens":1024}}',
            'parsed_rows' => null,
        ];
    }
}
