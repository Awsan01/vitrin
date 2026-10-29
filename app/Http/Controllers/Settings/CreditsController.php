<?php

namespace App\Http\Controllers\Settings;

use App\Data\CreditPack;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditTransaction;
use App\Services\ListingGenerator;
use App\Services\Paddle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditsController extends Controller
{
    /**
     * Credit balance, the packs for sale, purchases and recent usage.
     */
    public function __invoke(Request $request, Paddle $paddle): Response
    {
        $transactions = $request->user()->creditTransactions()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (CreditTransaction $t) => [
                'id' => $t->id,
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'reason' => $t->reason,
                'description' => $t->description,
                'listing_id' => $t->listing_id,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        // Checkouts that were opened but never paid aren't purchases yet.
        $purchases = $request->user()->creditPurchases()
            ->where('status', '!=', PurchaseStatus::Pending)
            ->latest('completed_at')
            ->limit(20)
            ->get()
            ->map(fn (CreditPurchase $p) => [
                'id' => $p->id,
                'pack' => CreditPack::find($p->pack)->name ?? ucfirst($p->pack),
                'credits' => $p->credits,
                'amount' => $p->amount !== null && $p->currency !== null ? Paddle::formatMoney($p->amount, $p->currency) : null,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'completed_at' => $p->completed_at?->toIso8601String(),
                'invoice' => $p->paddle_transaction_id !== null,
            ]);

        return Inertia::render('settings/credits', [
            'balance' => $request->user()->credits,
            'transactions' => $transactions,
            'costs' => ListingGenerator::prices(),
            'packs' => $paddle->packs(),
            'purchases' => $purchases,
            'paddle' => $paddle->checkoutConfig(),
            // Why there's no Buy button, for whoever is setting the site up.
            // Only in debug mode, so a live site never lists its settings.
            'missingSettings' => config('app.debug') ? $paddle->missingSettings() : null,
        ]);
    }
}
