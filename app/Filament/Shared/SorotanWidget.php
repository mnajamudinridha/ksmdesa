<?php

namespace App\Filament\Shared;

use App\Support\Hari;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Companion of "Selamat datang" in the first dashboard row (a two-column grid): today's date in
 * village time, one line on what needs attention for the viewer, and a shortcut to where it is
 * handled. Without it "Selamat datang" sits alone in half of the row.
 */
abstract class SorotanWidget extends Widget
{
    /** Names listed before "dan N lainnya". */
    public const int NAMA_DITAMPILKAN = 3;

    /** Rendered with the page, like "Selamat datang", so the first row never jumps. */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.sorotan-hari-ini';

    /** @return array{keterangan: string, tautan?: string, labelTautan?: string, ikonTautan?: Heroicon} */
    abstract protected function sorotan(): array;

    /** @return array{judul: string, keterangan: string, tautan: ?string, labelTautan: ?string, ikonTautan: ?Heroicon} */
    protected function getViewData(): array
    {
        return [
            'judul' => Hari::ini()->translatedFormat('l, j F Y'),
            'tautan' => null,
            'labelTautan' => null,
            'ikonTautan' => null,
            ...$this->sorotan(),
        ];
    }

    /** @param  Collection<int, string>  $nama  e.g. "Andi, Budi, Sari dan 2 lainnya" */
    protected static function daftarNama(Collection $nama): string
    {
        $sisa = $nama->count() - self::NAMA_DITAMPILKAN;
        $tampil = $nama->take(self::NAMA_DITAMPILKAN)->implode(', ');

        return $sisa > 0 ? "{$tampil} dan {$sisa} lainnya" : $tampil;
    }
}
