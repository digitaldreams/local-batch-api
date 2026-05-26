<?php

namespace Tests\Unit;

use BatchApi\Shared\Batch\Enums\BatchStatus;
use PHPUnit\Framework\TestCase;

class BatchStatusTest extends TestCase
{
    public function test_pending_and_processing_are_not_terminal(): void
    {
        $this->assertFalse(BatchStatus::Pending->isTerminal());
        $this->assertFalse(BatchStatus::Processing->isTerminal());
    }

    public function test_completed_failed_cancelling_cancelled_expired_are_terminal(): void
    {
        $this->assertTrue(BatchStatus::Completed->isTerminal());
        $this->assertTrue(BatchStatus::Failed->isTerminal());
        $this->assertTrue(BatchStatus::Cancelling->isTerminal());
        $this->assertTrue(BatchStatus::Cancelled->isTerminal());
        $this->assertTrue(BatchStatus::Expired->isTerminal());
    }

    public function test_anthropic_processing_status_maps_correctly(): void
    {
        $this->assertSame('in_progress', BatchStatus::Pending->anthropicProcessingStatus());
        $this->assertSame('in_progress', BatchStatus::Processing->anthropicProcessingStatus());
        $this->assertSame('canceling', BatchStatus::Cancelling->anthropicProcessingStatus());
        $this->assertSame('ended', BatchStatus::Completed->anthropicProcessingStatus());
        $this->assertSame('ended', BatchStatus::Failed->anthropicProcessingStatus());
        $this->assertSame('ended', BatchStatus::Cancelled->anthropicProcessingStatus());
        $this->assertSame('ended', BatchStatus::Expired->anthropicProcessingStatus());
    }

    public function test_openai_status_maps_correctly(): void
    {
        $this->assertSame('validating', BatchStatus::Pending->openAiStatus());
        $this->assertSame('in_progress', BatchStatus::Processing->openAiStatus());
        $this->assertSame('completed', BatchStatus::Completed->openAiStatus());
        $this->assertSame('failed', BatchStatus::Failed->openAiStatus());
        $this->assertSame('cancelling', BatchStatus::Cancelling->openAiStatus());
        $this->assertSame('cancelled', BatchStatus::Cancelled->openAiStatus());
        $this->assertSame('expired', BatchStatus::Expired->openAiStatus());
    }
}
