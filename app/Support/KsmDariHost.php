<?php

namespace App\Support;

use App\Models\Ksm;
use Illuminate\Support\Str;

/**
 * The single rule for "which KSM does this host belong to": <slug>.<APP_DOMAIN> → Ksm.
 * Used wherever the tenant is needed before Filament identifies it (middleware, login,
 * branding on the login page, the super admin's default tenant). Do not re-implement it.
 */
final class KsmDariHost
{
    public static function dari(string $host): ?Ksm
    {
        $slug = Str::beforeLast($host, '.'.config('app.domain'));

        // Apex or foreign host (nothing stripped), or deeper than one label.
        if ($slug === $host || $slug === '' || str_contains($slug, '.')) {
            return null;
        }

        // Memoised per host for the request: branding closures run several times per render.
        return once(fn (): ?Ksm => Ksm::query()->where('slug', $slug)->first());
    }

    public static function dariRequest(): ?Ksm
    {
        return self::dari(request()->getHost());
    }
}
