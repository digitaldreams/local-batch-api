<?php

namespace Tests\Feature\OpenAi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadFileTest extends TestCase
{
    use RefreshDatabase;

    private function jsonlContent(): string
    {
        return '{"custom_id":"req-1","method":"POST","url":"/v1/chat/completions","body":{"model":"llama3.2","messages":[{"role":"user","content":"Hello"}],"max_tokens":1024}}';
    }

    public function test_uploads_jsonl_file_and_returns_file_object(): void
    {
        $file = UploadedFile::fake()->createWithContent('requests.jsonl', $this->jsonlContent());

        $response = $this->post('/api/openai/v1/files', [
            'file' => $file,
            'purpose' => 'batch',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'object', 'purpose', 'created_at'])
            ->assertJsonPath('object', 'file')
            ->assertJsonPath('purpose', 'batch');

        $this->assertStringStartsWith('file-', $response->json('id'));
    }

    public function test_validates_purpose_is_batch(): void
    {
        $file = UploadedFile::fake()->createWithContent('requests.jsonl', $this->jsonlContent());

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/openai/v1/files', [
                'file' => $file,
                'purpose' => 'fine-tune',
            ])->assertUnprocessable();
    }

    public function test_validates_file_required(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/openai/v1/files', ['purpose' => 'batch'])
            ->assertUnprocessable();
    }
}
