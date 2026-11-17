<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureKsmSubdomain;
use App\Models\Ksm;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
        return $panel
            ->id('ksm')
            ->path('')
            ->login()
            ->tenant(Ksm::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.'.config('app.domain'))
            ->brandName(fn (): string => Filament::getTenant()?->nama ?? 'KSM Desa')
            ->colors([
                'primary' => Color::Green,
            ])
            // Same order as the dashboard mockup's sidebar.
            ->navigationGroups(['Data Input', 'Laporan', 'Data Master'])
            ->discoverResources(in: app_path('Filament/Ksm/Resources'), for: 'App\Filament\Ksm\Resources')
            ->discoverPages(in: app_path('Filament/Ksm/Pages'), for: 'App\Filament\Ksm\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Ksm/Widgets'), for: 'App\Filament\Ksm\Widgets')
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
