<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Shared\ProfilSaya;
use App\Filament\Shared\TampilanPanel;
use App\Models\PengaturanMaster;
use Filament\Auth\Pages\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->domain(config('app.domain'))
            ->path('admin')
            ->login()
            ->profile(ProfilSaya::class, isSimple: false)
            // Initials drawn locally: the default provider would send names to ui-avatars.com.
            ->defaultAvatarProvider(InisialAvatar::class)
            ->brandName('KSM Desa — Master')
            ->brandLogo(fn (): string|Htmlable|null => TampilanPanel::logo(PengaturanMaster::sekarang()->logoUrl(), 'KSM Desa — Master'))
            ->brandLogoHeight(fn (): string => TampilanPanel::tinggiLogo())
            ->favicon(fn (): string => TampilanPanel::favicon(PengaturanMaster::sekarang()->logoUrl()))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => TampilanPanel::gayaLatarLogin(PengaturanMaster::sekarang()->latarLoginUrl()), scopes: Login::class)
            ->colors([
                'primary' => Color::Green,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
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
