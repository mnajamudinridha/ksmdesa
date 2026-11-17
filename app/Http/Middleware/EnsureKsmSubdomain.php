<?php

namespace App\Http\Middleware;

use App\Support\KsmDariHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament registers the KSM panel's auth routes (/login, /logout, password reset)
 * without a domain, so they would answer on every host the wildcard vhost serves:
 * the master apex and any made-up subdomain. Show the KSM panel only on the
 * subdomain of a KSM that actually exists — no stray login pages to confuse people.
 */
class EnsureKsmSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(KsmDariHost::dariRequest() === null, 404);

        return $next($request);
    }
}
