<?php

namespace App\Http\Controllers;

use App\Data\CreditPack;
use App\Services\CreditPurchases;
use App\Services\Paddle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CreditPurchaseController extends Controller
{
    /**
     * Record a pending purchase and hand the browser what it needs to open
     * Paddle's checkout for it.
     */
    public function store(Request $request, Paddle $paddle, CreditPurchases $purchases): RedirectResponse
    {
        $validated = $request->validate([
            'pack' => ['required', 'string', Rule::in(array_map(fn (CreditPack $p) => $p->key, CreditPack::purchasable()))],
        ]);

        $pack = CreditPack::find($validated['pack']);

        if (! $paddle->enabled() || $pack === null || $pack->priceId === null) {
            return $this->toast('error', "Payments aren't available right now. Please try again later.");
        }

        $purchase = $purchases->start($request->user(), $pack);

        Inertia::flash('checkout', [
            'purchase_id' => $purchase->id,
            'price_id' => $pack->priceId,
            'email' => $request->user()->email,
        ]);

        return back();
    }
}
