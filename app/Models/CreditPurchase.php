<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One credit pack bought through Paddle. Created when the seller opens the
 * checkout, so the webhook can match the payment to the right account and
 * pack without trusting anything the browser sent.
 *
 * @property string $id
 * @property int $user_id
 * @property string $pack
 * @property string $price_id
 * @property int $credits Total credits this purchase adds.
 * @property int $credits_reversed Credits taken back by refunds and chargebacks.
 * @property PurchaseStatus $status
 * @property string|null $paddle_transaction_id
 * @property string|null $paddle_customer_id
 * @property int|null $amount What the customer paid, in the currency's smallest unit.
 * @property string|null $currency
 * @property list<string>|null $adjustment_ids Paddle adjustments already applied.
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'pack', 'price_id', 'credits', 'credits_reversed', 'status', 'paddle_transaction_id',
    'paddle_customer_id', 'amount', 'currency', 'adjustment_ids', 'completed_at',
])]
class CreditPurchase extends Model
{
    use HasUlids, Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'credits' => 'integer',
            'credits_reversed' => 'integer',
            'amount' => 'integer',
            'adjustment_ids' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Checkouts that were opened and never paid. A month leaves room for
     * slow payment methods to confirm.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('status', PurchaseStatus::Pending)
            ->where('created_at', '<', now()->subMonth());
    }
}
