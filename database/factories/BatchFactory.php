<?php

namespace BatchApi\Database\Factories;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use BatchApi\Shared\Batch\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'provider_format' => 'anthropic',
            'status' => BatchStatus::Pending,
            'payload' => [
                [
                    'custom_id' => 'req-1',
                    'model' => 'llama3.2',
                    'max_tokens' => 1024,
                    'messages' => [
                        ['role' => 'user', 'content' => fake()->sentence()],
                    ],
                ],
            ],
            'raw_response' => null,
            'request_count' => 1,
            'succeeded_count' => 0,
            'errored_count' => 0,
            'expires_at' => now()->addHours(24),
            'completed_at' => null,
        ];
    }
}
