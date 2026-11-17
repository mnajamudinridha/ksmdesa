<?php

namespace App\Filament\Shared;

use App\Models\Wilayah;
use Filament\Facades\Filament;

/**
 * Select options from the KSM's master lists, always explicitly limited to the current KSM
 * (never relying on the implicit tenant scope). The options are the valid set, so the Select's
 * built-in "in" rule rejects ids from other lists or other KSMs on the server. Each method also
 * takes the value already saved on the record, so saving an unrelated edit never fails.
 */
final class PilihanMaster
{
    /** @return array<int, string> RT/RW of this KSM, in RW then RT order */
    public static function wilayah(?int $tersimpan = null): array
    {
        // An RT has no inactive state, so the saved value is always among the options.
        return Wilayah::query()
            ->whereBelongsTo(Filament::getTenant())
            ->urut()
            ->get()
            ->mapWithKeys(fn (Wilayah $wilayah): array => [$wilayah->getKey() => $wilayah->label()])
            ->all();
    }
}
