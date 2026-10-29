<?php

namespace App\Http\Controllers;

use App\Data\CreditPack;
use App\Models\CreditPurchase;
use App\Services\CreditPurchases;
use App\Services\Paddle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

    /**
     * Called by the browser after the checkout reports a payment: checks it
     * with Paddle and adds the credits without waiting for the webhook, then
     * returns to the Credits page with the new balance.
     */
    public function confirm(Request $request, CreditPurchase $purchase, CreditPurchases $purchases): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'regex:/^txn_[a-z0-9]{26}$/'],
        ]);

        try {
            $purchases->confirm($purchase, $validated['transaction_id']);
        } catch (ConnectionException|RequestException $e) {
            // The webhook, or the scheduled paddle:reconcile, adds the credits instead.
            report($e);
        }

        // Not back(): without a Referer that could be a page from another tab.
        return to_route('credits.index');
    }

    /**
     * Download the invoice Paddle issued for a purchase.
     */
    public function invoice(CreditPurchase $purchase, Paddle $paddle): RedirectResponse
    {
        abort_if($purchase->paddle_transaction_id === null, 404);

        try {
            $url = $paddle->invoiceUrl($purchase->paddle_transaction_id);
        } catch (ConnectionException|RequestException $e) {
            report($e);
            $url = null;
        }

        return $url !== null
            ? redirect()->away($url)
            : $this->toast('error', "The invoice couldn't be loaded. Please try again in a minute.");
    }
}
