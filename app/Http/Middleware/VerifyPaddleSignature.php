<?php

namespace App\Http\Middleware;

use App\Support\PaddleSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets through webhooks that Paddle signed. Without a configured secret
 * every webhook is refused, so a missing setting can never turn the endpoint
 * into a way to add free credits.
 */
class VerifyPaddleSignature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.paddle.webhook_secret');

        if ($secret === '') {
            Log::error('Paddle webhook refused: PADDLE_WEBHOOK_SECRET is not set.');

            // 5xx makes Paddle retry, so payments still arrive once it's set.
            abort(503);
        }

        $valid = PaddleSignature::verify(
            $request->header('Paddle-Signature'),
            $request->getContent(),
            $secret,
            (int) config('services.paddle.webhook_tolerance'),
        );

        abort_unless($valid, 403);

        return $next($request);
    }
}
