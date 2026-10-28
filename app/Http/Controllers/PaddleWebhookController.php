<?php

namespace App\Http\Controllers;

use App\Services\CreditPurchases;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaddleWebhookController extends Controller
{
    /**
     * Handle a webhook from Paddle. The signature has already been checked
     * (VerifyPaddleSignature). Events Vitrin doesn't use still get a 200, so
     * Paddle doesn't keep retrying them.
     */
    public function __invoke(Request $request, CreditPurchases $purchases): Response
    {
        $data = (array) $request->json('data', []);

        match ($request->json('event_type')) {
            'transaction.completed' => $purchases->fulfil($data),
            'adjustment.created', 'adjustment.updated' => $purchases->reverse($data),
            default => null,
        };

        return response('', 200);
    }
}
