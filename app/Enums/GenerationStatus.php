<?php

namespace App\Enums;

/**
 * Status of a single generated item: one listing copy or one photo.
 */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
