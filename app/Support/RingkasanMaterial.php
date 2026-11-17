<?php

namespace App\Support;

use App\Enums\AktivitasMaterial;
use App\Enums\KelompokMaterial;
use App\Models\CatatanMaterial;
use App\Models\JenisMaterial;
use App\Models\Ksm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Material figures of one KSM. "Terpulihkan" (kept out of the dump) = sorted + processed;
 * a sale moves material that was already sorted, so it is never added to that. Stock = sorted
 * − sold, per kind. Sales values are cash income on their date (read by RingkasanKas).
 * Every query is limited to the given KSM; dates are village dates compared as Y-m-d strings.
 */
final class RingkasanMaterial
{
    public const string TANPA_JENIS = 'Tanpa jenis';

    public function __construct(private readonly Ksm $ksm) {}

    public function terpulihkan(string $dari, string $sampai): float
    {
        return round((float) $this->catatan($dari, $sampai)->whereIn('aktivitas', [AktivitasMaterial::Pilah, AktivitasMaterial::Olah])->sum('berat_kg'), 1);
    }

    public function diolah(string $dari, string $sampai): float
    {
        return round((float) $this->catatan($dari, $sampai)->where('aktivitas', AktivitasMaterial::Olah)->sum('berat_kg'), 1);
    }

    /** @return array{kg: float, nilai: int, jumlah: int} */
    public function terjual(string $dari, string $sampai): array
    {
        $jual = $this->catatan($dari, $sampai)->where('aktivitas', AktivitasMaterial::Jual);

        return [
            'kg' => round((float) (clone $jual)->sum('berat_kg'), 1),
            'nilai' => (int) (clone $jual)->sum('nilai_jual'),
            'jumlah' => (clone $jual)->count(),
        ];
    }

    /** @return array<string, float> group label => recovered kg, in the groups' order, empty groups left out */
    public function perKelompok(string $dari, string $sampai): array
    {
        $perKelompok = $this->catatan($dari, $sampai)
            ->whereIn('aktivitas', [AktivitasMaterial::Pilah, AktivitasMaterial::Olah])
            ->with('jenisMaterial')
            ->get(['id', 'jenis_material_id', 'berat_kg'])
            ->groupBy(fn (CatatanMaterial $catatan): string => $catatan->jenisMaterial?->kelompok->value ?? '')
            ->map(fn (Collection $catatan): float => round((float) $catatan->sum('berat_kg'), 1));

        $hasil = [];

        foreach ([...array_map(fn (KelompokMaterial $k): string => $k->value, KelompokMaterial::cases()), ''] as $kunci) {
            if (($perKelompok[$kunci] ?? 0) > 0) {
                $hasil[$kunci === '' ? self::TANPA_JENIS : KelompokMaterial::from($kunci)->getLabel()] = $perKelompok[$kunci];
            }
        }

        return $hasil;
    }

    /**
     * Recovered kg per group per month (stacked chart).
     *
     * @param  list<CarbonImmutable>  $bulan  first days of the months, oldest first
     * @return array<string, list<float>> group label => kg per month
     */
    public function perKelompokPerBulan(array $bulan): array
    {
        $perBulan = array_map(fn (CarbonImmutable $awal): array => $this->perKelompok($awal->toDateString(), $awal->endOfMonth()->toDateString()), $bulan);
        $kelompok = array_values(array_unique(array_merge(...array_map('array_keys', $perBulan ?: [[]]))));

        return collect($kelompok)
            ->mapWithKeys(fn (string $label): array => [$label => array_map(fn (array $satu): float => $satu[$label] ?? 0.0, $perBulan)])
            ->all();
    }

    /**
     * Per material kind: the kinds that are active or have any record up to the period's end,
     * plus "Tanpa jenis" when records lost their kind. Stock counts every record up to the end.
     *
     * @return list<array{jenis: string, kelompok: ?string, pilah: float, olah: float, terpulihkan: float, jual_kg: float, nilai: int, stok: float}>
     */
    public function perJenis(Periode $periode): array
    {
        $dari = $periode->dariString();
        $sampai = $periode->sampaiString();

        $catatan = CatatanMaterial::query()
            ->whereBelongsTo($this->ksm)
            ->whereDate('tanggal', '<=', $sampai)
            ->get(['id', 'tanggal', 'aktivitas', 'jenis_material_id', 'berat_kg', 'nilai_jual']);

        $perKunci = $catatan->groupBy(fn (CatatanMaterial $satu): string => (string) ($satu->jenis_material_id ?? ''));

        $jenis = JenisMaterial::query()
            ->whereBelongsTo($this->ksm)
            ->where(fn (Builder $query) => $query->where('aktif', true)->orWhereIn('id', $perKunci->keys()->filter()->all()))
            ->orderBy('nama')
            ->get();

        $baris = $jenis->map(fn (JenisMaterial $satu): array => self::barisJenis($satu->nama, $satu->kelompok->getLabel(), $perKunci[(string) $satu->getKey()] ?? collect(), $dari))->all();

        if ($perKunci->has('')) {
            $baris[] = self::barisJenis(self::TANPA_JENIS, null, $perKunci[''], $dari);
        }

        return $baris;
    }

    /**
     * Sales in the period per buyer, "Tanpa pengepul" last.
     *
     * @return list<array{mitra: string, transaksi: int, kg: float, nilai: int}>
     */
    public function perPengepul(Periode $periode): array
    {
        return $this->catatan($periode->dariString(), $periode->sampaiString())
            ->where('aktivitas', AktivitasMaterial::Jual)
            ->with('pengepul')
            ->get(['id', 'mitra_id', 'berat_kg', 'nilai_jual'])
            ->groupBy(fn (CatatanMaterial $catatan): string => $catatan->pengepul?->nama ?? 'Tanpa pengepul')
            ->map(fn (Collection $catatan, string $nama): array => [
                'mitra' => $nama,
                'transaksi' => $catatan->count(),
                'kg' => round((float) $catatan->sum('berat_kg'), 1),
                'nilai' => (int) $catatan->sum('nilai_jual'),
            ])
            ->sortBy(fn (array $baris): string => ($baris['mitra'] === 'Tanpa pengepul' ? '1' : '0').mb_strtolower($baris['mitra']))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CatatanMaterial>  $catatan  every record of the kind up to the period's end
     * @return array{jenis: string, kelompok: ?string, pilah: float, olah: float, terpulihkan: float, jual_kg: float, nilai: int, stok: float}
     */
    private static function barisJenis(string $nama, ?string $kelompok, Collection $catatan, string $dari): array
    {
        $diPeriode = $catatan->filter(fn (CatatanMaterial $satu): bool => $satu->tanggal->toDateString() >= $dari);
        $berat = fn (Collection $daftar, AktivitasMaterial $aktivitas): float => round((float) $daftar->where('aktivitas', $aktivitas)->sum('berat_kg'), 1);

        $pilah = $berat($diPeriode, AktivitasMaterial::Pilah);
        $olah = $berat($diPeriode, AktivitasMaterial::Olah);

        return [
            'jenis' => $nama,
            'kelompok' => $kelompok,
            'pilah' => $pilah,
            'olah' => $olah,
            'terpulihkan' => round($pilah + $olah, 1),
            'jual_kg' => $berat($diPeriode, AktivitasMaterial::Jual),
            'nilai' => (int) $diPeriode->where('aktivitas', AktivitasMaterial::Jual)->sum('nilai_jual'),
            'stok' => round($berat($catatan, AktivitasMaterial::Pilah) - $berat($catatan, AktivitasMaterial::Jual), 1),
        ];
    }

    /** @return Builder<CatatanMaterial> */
    private function catatan(string $dari, string $sampai): Builder
    {
        return CatatanMaterial::query()
            ->whereBelongsTo($this->ksm)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
    }
}
