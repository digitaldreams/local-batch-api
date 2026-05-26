<?php

namespace BatchApi\OpenAi\Files;

use BatchApi\Shared\Batch\Models\BatchFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class UploadFileController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'purpose' => ['required', 'string', 'in:batch'],
        ]);

        $content = $request->file('file')->get();

        $file = BatchFile::create([
            'id' => 'file-'.Str::uuid(),
            'purpose' => $request->input('purpose'),
            'content' => $content,
        ]);

        return response()->json([
            'id' => $file->id,
            'object' => 'file',
            'purpose' => $file->purpose,
            'created_at' => $file->created_at->timestamp,
        ], 201);
    }
}
