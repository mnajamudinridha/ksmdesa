<?php

namespace App\Filament\Ksm\Pages\Analitik;

use App\Filament\Ksm\Pages\Concerns\PunyaFilterPeriode;
use Filament\Pages\Dashboard;
use UnitEnum;

/**
 * An analytics dashboard (Keuangan, Operasional, …): a period filter and widgets that read it.
 * Each page sets $routePath and $slug to the same string, e.g. 'analitik/operasional'.
 */
abstract class HalamanAnalitik extends Dashboard
{
    use PunyaFilterPeriode;

    protected static string|UnitEnum|null $navigationGroup = 'Analitik';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
