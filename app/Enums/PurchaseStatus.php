<?php

namespace App\Enums;

/**
 * Where a credit pack purchase stands. A purchase starts pending when the
 * seller opens the checkout and completes once Paddle confirms the payment.
 */
enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Completed => 'Paid',
            self::PartiallyRefunded => 'Partly refunded',
            self::Refunded => 'Refunded',
        };
    }
}
