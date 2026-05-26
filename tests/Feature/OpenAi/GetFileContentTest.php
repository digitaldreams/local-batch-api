<?php

namespace Tests\Feature\OpenAi;

use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetFileContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_jsonl_file_content(): void
    {
        $content = '{"custom_id":"req-1","response":{"status_code":200,"body":{"choices":[{"message":{"role":"assistant","content":"Hello"}}]}}}';
        $file = BatchFile::factory()->create([
            'purpose' => 'batch_output',
            'content' => $content,
        ]);

        $response = $this->get("/api/openai/v1/files/{$file->id}/content");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/x-ndjson');

        $this->assertSame($content, $response->streamedContent());
    }

    public function test_returns_204_when_file_has_no_content(): void
    {
        $file = BatchFile::factory()->create(['content' => '']);

        $this->get("/api/openai/v1/files/{$file->id}/content")
            ->assertNoContent();
    }

    public function test_returns_404_for_unknown_file(): void
    {
        $this->get('/api/openai/v1/files/file-nonexistent/content')
            ->assertNotFound();
    }
}
