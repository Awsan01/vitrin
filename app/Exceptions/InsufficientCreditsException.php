<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $needed, public readonly int $available)
    {
        parent::__construct("This needs {$needed} credits but you have {$available}.");
    }
}
