<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response (Phase 32).
 *
 * CSP: third-party origins are pinned (Google Fonts only), plugins and
 * foreign framing are blocked, base-uri / form-action stay on this origin.
 * script-src still allows inline + eval: Alpine evaluates expressions and ~44
 * views use inline confirm()/print() handlers.
 * ponytail: move those handlers to Alpine directives, then switch script-src
 * to a Vite nonce and drop 'unsafe-inline'.
 *
 * Skipped while the Vite dev server is running (public/hot), which serves
 * assets from another port.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! is_file(public_path('hot'))) {
            $headers->set('Content-Security-Policy', implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob: https:",
                "media-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                // Browsers apply form-action to the redirect after a submit: paying
                // posts here, then redirects to PayMongo's hosted checkout.
                "form-action 'self' https://*.paymongo.com",
                "frame-ancestors 'self'",
                app()->isProduction() ? 'upgrade-insecure-requests' : null,
            ])));
        }

        return $response;
    }
}
