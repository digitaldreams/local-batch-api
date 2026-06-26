<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_batch_api_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider_format');
            $table->string('status')->default('pending');
            $table->json('payload');
            $table->json('raw_response')->nullable();
            $table->string('input_file_id')->nullable();
            $table->string('output_file_id')->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('succeeded_count')->default(0);
            $table->unsignedInteger('errored_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_batch_api_batches');
    }
};
