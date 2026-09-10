<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melindungi endpoint khusus aplikasi desktop (Electron).
 * Kunci dikirim lewat header X-Desktop-Key dan dibandingkan dengan
 * APP_DESKTOP_KEY / DESKTOP_API_KEY pada env runtime.
 * 404 (bukan 401) agar keberadaan endpoint tidak bocor ke publik.
 */
class CheckDesktopKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('app.desktop_key');
        $providedKey = (string) $request->headers->get('X-Desktop-Key');

        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            abort(404);
        }

        return $next($request);
    }
}
