<?php

namespace App\Enums;

/**
 * Overall status of a listing, derived from its analysis step and items.
 */
enum ListingStatus: string
{
    case Queued = 'queued';
    case Analyzing = 'analyzing';
    case Generating = 'generating';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function isProcessing(): bool
    {
        return in_array($this, [self::Queued, self::Analyzing, self::Generating], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Analyzing => 'Analyzing photo',
            self::Generating => 'Generating',
            self::Completed => 'Ready',
            self::Partial => 'Ready with errors',
            self::Failed => 'Failed',
        };
    }
}
