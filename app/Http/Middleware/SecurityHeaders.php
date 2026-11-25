<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every response, including error pages.
 * Each one answers a finding in the Nov 2026 Qualys scan.
 */
class SecurityHeaders
{
    /**
     * Hosts the views load scripts, styles and fonts from. Keep in sync with
     * the <link>/<script> tags in the layouts if a library is added.
     */
    private const CDNS = 'https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com https://code.jquery.com';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        $cdns = self::CDNS;

        // 'unsafe-inline' stays because the views use inline <script>/<style>
        // blocks and onclick handlers; the policy still pins every external
        // source to the known CDNs, blocks plugins, framing and form posts
        // to other origins (QID 150206).
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' {$cdns}",
            "style-src 'self' 'unsafe-inline' {$cdns}",
            "font-src 'self' data: {$cdns}",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            // QID 150248 — the app uses none of these browser features.
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()',
            // QIDs 531930 / 531932 / 531928.
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Embedder-Policy' => 'credentialless',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        // QID 150135 — one year. Only meaningful (and only honoured by
        // browsers) over HTTPS, so not sent on plain-HTTP local dev.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
