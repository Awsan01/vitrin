<?php

namespace App\Services;

use App\Data\CreditPack;
use App\Enums\PurchaseStatus;
use App\Models\CreditPurchase;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Buying credit packs through Paddle.
 *
 * Flow: start() records a pending purchase and the browser opens Paddle's
 * checkout with its ID as custom data. Paddle then sends signed webhooks:
 * fulfil() adds the credits when the payment completes, and reverse() takes
 * them back after a refund or chargeback.
 *
 * Nothing the browser sends decides how many credits are added: the pack and
 * price come from the purchase row, and the payment must be for that exact
 * Paddle price. Every step runs under a row lock and remembers what it has
 * applied, so Paddle retrying a webhook never adds or removes credits twice.
 */
class CreditPurchases
{
    public function __construct(protected CreditLedger $ledger) {}

    public function start(User $user, CreditPack $pack): CreditPurchase
    {
        return $user->creditPurchases()->create([
            'pack' => $pack->key,
            'price_id' => (string) $pack->priceId,
            'credits' => $pack->credits,
            'status' => PurchaseStatus::Pending,
        ]);
    }

    /**
     * Add the credits for a completed Paddle transaction.
     *
     * @param  array<string, mixed>  $transaction  The webhook's "data" object.
     */
    public function fulfil(array $transaction): void
    {
        $transactionId = data_get($transaction, 'id');
        $purchaseId = data_get($transaction, 'custom_data.purchase_id');

        if (data_get($transaction, 'status') !== 'completed' || ! is_string($transactionId)) {
            return;
        }

        if (! is_string($purchaseId)) {
            // Sold outside the app, e.g. an invoice made in the Paddle dashboard.
            Log::info('Paddle transaction without a Vitrin purchase', ['transaction' => $transactionId]);

            return;
        }

        DB::transaction(function () use ($transaction, $transactionId, $purchaseId) {
            $purchase = CreditPurchase::query()->whereKey($purchaseId)->lockForUpdate()->first();

            if ($purchase === null) {
                Log::warning('Paddle transaction for an unknown purchase', ['transaction' => $transactionId, 'purchase' => $purchaseId]);

                return;
            }

            if ($purchase->status !== PurchaseStatus::Pending) {
                return;
            }

            $quantity = collect((array) data_get($transaction, 'items', []))
                ->filter(fn ($item) => data_get($item, 'price.id') === $purchase->price_id)
                ->sum(fn ($item) => (int) data_get($item, 'quantity', 0));

            if ($quantity < 1) {
                // The checkout was changed to another price (custom data is
                // editable in the browser). Leave it for a person to check.
                Log::warning('Paddle transaction does not match its purchase', ['transaction' => $transactionId, 'purchase' => $purchase->id]);

                return;
            }

            $credits = $purchase->credits * $quantity;
            $amount = data_get($transaction, 'details.totals.total');

            $purchase->update([
                'status' => PurchaseStatus::Completed,
                'credits' => $credits,
                'paddle_transaction_id' => $transactionId,
                'paddle_customer_id' => data_get($transaction, 'customer_id'),
                'amount' => is_numeric($amount) ? (int) $amount : null,
                'currency' => data_get($transaction, 'currency_code'),
                'completed_at' => now(),
            ]);

            $this->ledger->add(
                $purchase->user,
                $credits,
                CreditTransaction::PURCHASE,
                "Bought the {$this->packName($purchase)} pack",
                reference: "paddle:{$transactionId}",
            );
        });
    }

    /**
     * Take credits back after an approved refund or a chargeback.
     *
     * @param  array<string, mixed>  $adjustment  The webhook's "data" object.
     */
    public function reverse(array $adjustment): void
    {
        $adjustmentId = data_get($adjustment, 'id');
        $transactionId = data_get($adjustment, 'transaction_id');
        $action = data_get($adjustment, 'action');
        $status = data_get($adjustment, 'status');

        $applies = match ($action) {
            'refund' => $status === 'approved',
            // Paddle doesn't document a chargeback's first status, and the
            // bank has already taken the money back, so anything not
            // rejected or reversed counts.
            'chargeback' => ! in_array($status, ['rejected', 'reversed'], true),
            default => false,
        };

        if (! $applies || ! is_string($adjustmentId) || ! is_string($transactionId)) {
            return;
        }

        DB::transaction(function () use ($adjustment, $adjustmentId, $transactionId, $action) {
            $purchase = CreditPurchase::query()->where('paddle_transaction_id', $transactionId)->lockForUpdate()->first();

            if ($purchase === null) {
                Log::warning('Paddle adjustment for an unknown transaction', ['adjustment' => $adjustmentId, 'transaction' => $transactionId]);

                return;
            }

            $applied = $purchase->adjustment_ids ?? [];

            if (in_array($adjustmentId, $applied, true)) {
                return;
            }

            $remaining = $purchase->credits - $purchase->credits_reversed;
            $refunded = data_get($adjustment, 'totals.total');

            // A partial refund takes back the same share of the credits.
            $credits = data_get($adjustment, 'type') !== 'full' && $purchase->amount && is_numeric($refunded)
                ? (int) round($purchase->credits * (int) $refunded / $purchase->amount)
                : $remaining;
            $credits = max(0, min($remaining, $credits));

            $what = $action === 'chargeback' ? 'charged back' : 'refunded';
            $removed = $credits > 0
                ? $this->ledger->reverse($purchase->user, $credits, "{$this->packName($purchase)} pack {$what}", "paddle:{$adjustmentId}")
                : 0;

            $reversed = $purchase->credits_reversed + $credits;

            $purchase->update([
                'credits_reversed' => $reversed,
                'status' => $reversed >= $purchase->credits ? PurchaseStatus::Refunded : PurchaseStatus::PartiallyRefunded,
                'adjustment_ids' => [...$applied, $adjustmentId],
            ]);

            if ($removed < $credits) {
                Log::warning('Credits from a refunded purchase were already spent', [
                    'user' => $purchase->user_id,
                    'purchase' => $purchase->id,
                    'action' => $action,
                    'to_remove' => $credits,
                    'removed' => $removed,
                ]);
            }
        });
    }

    protected function packName(CreditPurchase $purchase): string
    {
        return CreditPack::find($purchase->pack)->name ?? ucfirst($purchase->pack);
    }
}
