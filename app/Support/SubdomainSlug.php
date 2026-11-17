<?php

namespace App\Support;

/**
 * Rules for a KSM slug, which becomes the subdomain <slug>.<APP_DOMAIN>.
 */
final class SubdomainSlug
{
    /**
     * Names the server already answers for (DirectAdmin creates these DNS records and
     * vhosts for every domain) or that the platform keeps for itself. A KSM using one
     * would be unreachable or would shadow a real service.
     */
    public const RESERVED = [
        'www', 'mail', 'webmail', 'ftp', 'pop', 'smtp', 'imap',  // DirectAdmin services
        'admin', 'api', 'app', 'master', 'panel', 'static', 'cdn',
        'deploy-healthcheck',                                     // used by deploy/deploy.sh
    ];

    /** A single lowercase DNS label: letters, digits, inner hyphens, 1–63 chars. */
    public static function isWellFormed(string $slug): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug) === 1;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }

    /** Validation message, or null when the slug is acceptable. */
    public static function problem(string $slug): ?string
    {
        return match (true) {
            ! self::isWellFormed($slug) => 'Hanya huruf kecil, angka, dan tanda hubung (bukan di awal/akhir), maksimal 63 karakter.',
            self::isReserved($slug) => "Nama \"{$slug}\" dipakai sistem, pilih nama lain.",
            default => null,
        };
    }
}
