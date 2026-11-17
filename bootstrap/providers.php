<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\KsmPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    KsmPanelProvider::class,
];
