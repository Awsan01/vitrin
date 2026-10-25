<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry in a user's credit ledger. Positive amounts add credits,
 * negative amounts spend them.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $listing_id
 * @property int $amount
 * @property int $balance_after
 * @property string $reason
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['listing_id', 'amount', 'balance_after', 'reason', 'description'])]
class CreditTransaction extends Model
{
    public const SIGNUP = 'signup';

    public const GENERATION = 'generation';

    public const REFUND = 'refund';

    public const GRANT = 'grant';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
