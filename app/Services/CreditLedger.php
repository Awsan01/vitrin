<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every balance change goes through here, so users.credits and the
 * credit_transactions ledger always agree.
 */
class CreditLedger
{
    /**
     * Add credits (signup bonus, purchase, manual grant, refund).
     */
    public function add(User $user, int $amount, string $reason, ?string $description = null, ?Listing $listing = null): CreditTransaction
    {
        return $this->record($user, abs($amount), $reason, $description, $listing);
    }

    /**
     * Spend credits, failing if the balance is too low.
     *
     * @throws InsufficientCreditsException
     */
    public function spend(User $user, int $amount, string $description, ?Listing $listing = null): CreditTransaction
    {
        return $this->record($user, -abs($amount), CreditTransaction::GENERATION, $description, $listing);
    }

    public function refund(User $user, int $amount, string $description, ?Listing $listing = null): ?CreditTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return $this->record($user, $amount, CreditTransaction::REFUND, $description, $listing);
    }

    protected function record(User $user, int $amount, string $reason, ?string $description, ?Listing $listing): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $reason, $description, $listing) {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $balance = $locked->credits + $amount;

            if ($balance < 0) {
                throw new InsufficientCreditsException(abs($amount), $locked->credits);
            }

            $locked->forceFill(['credits' => $balance])->save();
            $user->credits = $balance;

            return $locked->creditTransactions()->create([
                'listing_id' => $listing?->getKey(),
                'amount' => $amount,
                'balance_after' => $balance,
                'reason' => $reason,
                'description' => $description,
            ]);
        });
    }
}
