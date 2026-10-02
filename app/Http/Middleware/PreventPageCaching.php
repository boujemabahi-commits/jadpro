<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * App pages (HTML) must never be cached by the browser, the host (LiteSpeed/LSCache on
 * Hostinger) or a CDN: a cached login page carries another visitor's CSRF token and session,
 * so signing in from a new computer fails with "page expired" or stays on the login page,
 * and after logout the back button could show private pages. Static assets (CSS/JS/fonts,
 * served directly by the web server) are not affected.
 */
class PreventPageCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $type = (string) $response->headers->get('Content-Type', '');
        if ($type === '' || str_contains($type, 'text/html') || str_contains($type, 'application/json')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            // LiteSpeed's own page cache (Hostinger) — never store app pages.
            $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
        }

        return $response;
    }
}
