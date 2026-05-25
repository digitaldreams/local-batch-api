<?php

namespace BatchApi\Shared\Batch\Enums;

enum BatchStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
    case Expired    = 'expired';

    public function anthropicProcessingStatus(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'in_progress',
            default                         => 'ended',
        };
    }

    public function openAiStatus(): string
    {
        return match ($this) {
            self::Pending    => 'validating',
            self::Processing => 'in_progress',
            self::Completed  => 'completed',
            self::Failed     => 'failed',
            self::Cancelled  => 'cancelled',
            self::Expired    => 'expired',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Expired]);
    }
}
