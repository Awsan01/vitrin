<?php

namespace App\Support;

/**
 * Checks the Paddle-Signature header on a webhook, as documented at
 * https://developer.paddle.com/webhooks/signature-verification.
 *
 * The header looks like "ts=1671552777;h1=eb4d0dc8...". Paddle signs
 * "{ts}:{raw body}" with HMAC-SHA256 using the notification destination's
 * secret. While a secret is being rotated, the header carries one h1 per
 * secret, so any match is accepted.
 */
class PaddleSignature
{
    public static function verify(?string $header, string $payload, string $secret, int $tolerance, ?int $now = null): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(';', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 'ts') {
                $timestamp = $value;
            } elseif ($key === 'h1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            return false;
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}:{$payload}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
