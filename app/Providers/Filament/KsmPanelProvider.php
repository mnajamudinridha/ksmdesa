<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Ksm\Pages\Auth\Login;
use App\Filament\Ksm\Pages\Tenancy\EditProfilKsm;
use App\Filament\Shared\ProfilSaya;
use App\Http\Middleware\EnsureKsmSubdomain;
use App\Models\Ksm;
use App\Support\KsmDariHost;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel for one KSM, served at the root of its subdomain: https://<slug>.<APP_DOMAIN>/.
 * The tenant (App\Models\Ksm) is resolved from the subdomain; sessions are host-only,
 * so signing in to one KSM never signs you in to another.
 */
class KsmPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // On the login page Filament has not identified the tenant yet: fall back to the host.
        $ksm = fn (): ?Ksm => Filament::getTenant() ?? KsmDariHost::dariRequest();

        return $panel
            ->id('ksm')
            ->path('')
            ->login(Login::class)
            // Simple layout: /profil-saya is not a tenant page, and the full layout's KSM switcher
            // (shown to master administrators) needs a tenant — it crashed with a 500.
            ->profile(ProfilSaya::class, isSimple: true)
            // Initials drawn locally: the default provider would send names to ui-avatars.com.
            ->defaultAvatarProvider(InisialAvatar::class)
            ->tenant(Ksm::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.'.config('app.domain'))
            ->tenantProfile(EditProfilKsm::class)
            // Village users belong to one KSM: never offer the others.
            ->tenantMenu(fn (): bool => (bool) auth()->user()?->is_super_admin)
            ->brandName(fn (): string => $ksm()?->nama ?? 'KSM Desa')
            ->brandLogo(fn (): string|Htmlable|null => self::logo($ksm()))
            // Guests are on the login/reset pages, where the heading already names the KSM:
            // the logo alone, larger. Inside the app: logo with the name beside it.
            ->brandLogoHeight(fn (): string => auth()->check() ? '2.75rem' : '6rem')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => self::gayaLatarLogin($ksm()), scopes: Login::class)
            ->colors([
                'primary' => Color::Green,
            ])
            // Same order as the dashboard mockup's sidebar.
            ->navigationGroups(['Data Input', 'Laporan', 'Data Master'])
            // The tenant menu (home of the default profile link) is hidden, so link the profile here.
            ->navigationItems([
                NavigationItem::make('Profil KSM')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->group('Data Master')
                    ->sort(90)
                    ->url(fn (): string => EditProfilKsm::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs(EditProfilKsm::getRouteName()))
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdminKsm()),
            ])
            ->discoverResources(in: app_path('Filament/Ksm/Resources'), for: 'App\Filament\Ksm\Resources')
            ->discoverPages(in: app_path('Filament/Ksm/Pages'), for: 'App\Filament\Ksm\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Ksm/Widgets'), for: 'App\Filament\Ksm\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EnsureKsmSubdomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private static function logo(?Ksm $ksm): string|Htmlable|null
    {
        if (blank($ksm?->logo)) {
            return null;
        }

        return auth()->check() ? view('filament.ksm.brand', ['ksm' => $ksm]) : $ksm->logoUrl();
    }

    /** The KSM's login background (one at random), darkened a little so the card stands out. */
    private static function gayaLatarLogin(?Ksm $ksm): string
    {
        $url = $ksm?->latarLoginUrl();

        if ($url === null) {
            return '';
        }

        return '<style>.fi-simple-layout{background:linear-gradient(rgba(15,23,42,.35),rgba(15,23,42,.35)),url("'.$url.'") center/cover no-repeat fixed;}</style>';
    }
}
