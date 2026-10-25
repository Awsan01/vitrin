<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections for every web response: no framing
 * (clickjacking), no MIME sniffing of uploaded or generated files, no full
 * URLs leaked to other sites, and HTTPS-only once the site is served over
 * HTTPS in production.
 */
class AddSecurityHeaders
{
    /**
     * @var array<string, string>
     */
    protected const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        // Only directives that can't break the app's own scripts and styles.
        'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
