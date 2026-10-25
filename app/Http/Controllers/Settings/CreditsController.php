<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditsController extends Controller
{
    /**
     * Credit balance and recent usage.
     */
    public function __invoke(Request $request): Response
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

        return Inertia::render('settings/credits', [
            'balance' => $request->user()->credits,
            'transactions' => $transactions,
            'costs' => [
                'copy' => config('vitrin.credits.copy'),
                'photo' => config('vitrin.credits.photo'),
            ],
        ]);
    }
}
