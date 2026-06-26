<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_batch_api_batch_files', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('purpose');
            $table->longText('content');
            $table->json('parsed_rows')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_batch_api_batch_files');
    }
};
