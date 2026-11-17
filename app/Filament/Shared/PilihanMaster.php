<?php

namespace App\Filament\Shared;

use App\Enums\JenisMitra;
use App\Enums\JenisTransaksi;
use App\Models\JenisMaterial;
use App\Models\KategoriTransaksi;
use App\Models\Mitra;
use App\Models\Wilayah;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Active categories of one kind, plus the saved one when it is of the same kind (it may have
     * been deactivated since). No kind chosen → nothing to pick.
     *
     * @return array<int, string>
     */
    public static function kategori(?JenisTransaksi $jenis, ?int $tersimpan = null): array
    {
        if ($jenis === null) {
            return [];
        }

        return KategoriTransaksi::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where('jenis', $jenis)
            ->where(fn (Builder $query) => $query->where('aktif', true)->when($tersimpan, fn (Builder $q) => $q->orWhereKey($tersimpan)))
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (KategoriTransaksi $kategori): array => [$kategori->getKey() => $kategori->nama.($kategori->aktif ? '' : ' (nonaktif)')])
            ->all();
    }

    /**
     * Every category of the KSM, both kinds, also inactive — for filtering lists.
     *
     * @return array<int, string>
     */
    public static function semuaKategori(): array
    {
        return KategoriTransaksi::query()
            ->whereBelongsTo(Filament::getTenant())
            ->orderBy('jenis')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (KategoriTransaksi $kategori): array => [$kategori->getKey() => "{$kategori->nama} ({$kategori->jenis->getLabel()})"])
            ->all();
    }

    /** @return array<int, string> active material kinds, plus the saved one */
    public static function jenisMaterial(?int $tersimpan = null): array
    {
        return JenisMaterial::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where(fn (Builder $query) => $query->where('aktif', true)->when($tersimpan, fn (Builder $q) => $q->orWhereKey($tersimpan)))
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (JenisMaterial $jenis): array => [$jenis->getKey() => $jenis->nama.($jenis->aktif ? '' : ' (nonaktif)')])
            ->all();
    }

    /** @return array<int, string> every material kind of the KSM, also inactive — for filtering lists */
    public static function semuaJenisMaterial(): array
    {
        return JenisMaterial::query()
            ->whereBelongsTo(Filament::getTenant())
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * Buyers for a sale: active partners of kind "pengepul", plus the saved one (it may have been
     * deactivated or changed kind since).
     *
     * @return array<int, string>
     */
    public static function pengepul(?int $tersimpan = null): array
    {
        return Mitra::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $aktif) => $aktif->where('jenis', JenisMitra::Pengepul)->where('aktif', true))
                ->when($tersimpan, fn (Builder $q) => $q->orWhereKey($tersimpan)))
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Mitra $mitra): array => [$mitra->getKey() => $mitra->nama.($mitra->aktif ? '' : ' (nonaktif)')])
            ->all();
    }

    /**
     * Every buyer of the KSM for filtering lists: partners of kind "pengepul" (also inactive) and
     * any partner that ever bought material (it may have changed kind since).
     *
     * @return array<int, string>
     */
    public static function semuaPengepul(): array
    {
        return Mitra::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where(fn (Builder $query) => $query->where('jenis', JenisMitra::Pengepul)->orWhereHas('catatanMaterial'))
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Mitra $mitra): array => [$mitra->getKey() => $mitra->nama.($mitra->aktif ? '' : ' (nonaktif)')])
            ->all();
    }

    /** @return array<int, string> active partners, plus the saved one */
    public static function mitra(?int $tersimpan = null): array
    {
        return Mitra::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where(fn (Builder $query) => $query->where('aktif', true)->when($tersimpan, fn (Builder $q) => $q->orWhereKey($tersimpan)))
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Mitra $mitra): array => [$mitra->getKey() => $mitra->nama.($mitra->aktif ? '' : ' (nonaktif)')])
            ->all();
    }
}
