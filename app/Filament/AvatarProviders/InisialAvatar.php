<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatar rendered locally as an SVG data URI. Replaces Filament's default
 * UiAvatarsProvider, which makes every browser fetch https://ui-avatars.com/api/?name=…
 * — i.e. would send residents' names to a third party.
 */
final class InisialAvatar implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        return self::untukNama((string) Filament::getNameForDefaultAvatar($record));
    }

    public static function untukNama(string $nama): string
    {
        $inisial = str($nama)
            ->trim()
            ->explode(' ')
            ->map(fn (string $kata): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $kata), 0, 1))
            ->filter()
            ->take(2)
            ->join('');

        $teks = htmlspecialchars(mb_strtoupper($inisial ?: '?'), ENT_XML1);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#15803d"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="26" font-weight="600">'.$teks.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
