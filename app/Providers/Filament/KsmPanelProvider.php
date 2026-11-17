<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Ksm\Pages\Auth\Login;
use App\Filament\Ksm\Pages\Tenancy\EditProfilKsm;
use App\Filament\Shared\ProfilSaya;
use App\Filament\Shared\TampilanPanel;
use App\Http\Middleware\EnsureKsmSubdomain;
use App\Models\Ksm;
use App\Support\KsmDariHost;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
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
            // Simple layout: /profil-saya is not a tenant page, and the full layout's tenant parts
            // need a tenant (the KSM switcher, since removed, crashed it with a 500).
            ->profile(ProfilSaya::class, isSimple: true)
            // Initials drawn locally: the default provider would send names to ui-avatars.com.
            ->defaultAvatarProvider(InisialAvatar::class)
            ->tenant(Ksm::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.'.config('app.domain'))
            ->tenantProfile(EditProfilKsm::class)
            // No KSM switcher for anyone. Village users belong to one KSM; for master administrators
            // every other KSM is another subdomain with its own host-only session, so switching led
            // to a login page. "Profil KSM", the menu's other entry, is in the sidebar (below).
            ->tenantMenu(false)
            ->brandName(fn (): string => $ksm()?->nama ?? 'KSM Desa')
            ->brandLogo(fn (): string|Htmlable|null => TampilanPanel::logo($ksm()?->logoUrl(), $ksm()?->nama ?? 'KSM Desa'))
            // Guests are on the login/reset pages, where the heading already names the KSM:
            // the logo alone, larger. Inside the app: logo with the name beside it.
            ->brandLogoHeight(fn (): string => TampilanPanel::tinggiLogo())
            ->favicon(fn (): string => TampilanPanel::favicon($ksm()?->logoUrl()))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => TampilanPanel::gayaLatarLogin($ksm()?->latarLoginUrl()), scopes: Login::class)
            ->colors([
                'primary' => Color::Green,
            ])
            // Same order as the dashboard mockup's sidebar.
            ->navigationGroups(['Analitik', 'Data Input', 'Laporan', 'Data Master'])
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
            // Includes App\Filament\Ksm\Pages\Dashboard, which replaces Filament's default dashboard.
            ->discoverPages(in: app_path('Filament/Ksm/Pages'), for: 'App\Filament\Ksm\Pages')
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
}
