<?php

namespace App\Filament\Shared;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Brand and login background for both panels. Guests are on the login/reset pages, where
 * the heading already names the panel: the logo alone, larger. Inside the app: the logo with
 * the name beside it.
 */
final class TampilanPanel
{
    public static function logo(?string $logoUrl, string $nama): string|Htmlable|null
    {
        if ($logoUrl === null) {
            return null;
        }

        return auth()->check() ? view('filament.brand', ['logoUrl' => $logoUrl, 'nama' => $nama]) : $logoUrl;
    }

    /** The browser tab shows the panel's logo; without one, the generic KSM leaf. */
    public static function favicon(?string $logoUrl): string
    {
        return $logoUrl ?? asset('favicon.svg');
    }

    public static function tinggiLogo(): string
    {
        return auth()->check() ? '2.75rem' : '6rem';
    }

    /**
     * The login background (url already passed through latarLoginUrl()'s path allow-list),
     * darkened a little so the card stands out.
     */
    public static function gayaLatarLogin(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        return '<style>.fi-simple-layout{background:linear-gradient(rgba(15,23,42,.35),rgba(15,23,42,.35)),url("'.$url.'") center/cover no-repeat fixed;}</style>';
    }
}
