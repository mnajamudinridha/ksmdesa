<?php

namespace App\Providers;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every upload field only accepts paths it uploaded itself or the record's own value.
        // The private "local" disk also holds residents' ledger CSVs (imports/) and photos;
        // a future field without this could be tampered into reading them.
        FileUpload::configureUsing(fn (FileUpload $component) => $component->preventFilePathTampering());
    }
}
