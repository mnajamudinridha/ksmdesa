<?php

namespace App\Support;

use App\Enums\AktivitasMaterial;
use App\Enums\JenisMitra;
use App\Enums\JenisTransaksi;
use App\Models\CatatanMaterial;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\TransaksiKas;
use Illuminate\Database\Eloquent\Builder;

/**
 * Partnership figures of one KSM: support = cash-book income naming the partner; sales = material
 * sold to the partner as buyer. One source for the cards, the chart and the report.
 */
final class RingkasanMitra
{
    public function __construct(private readonly Ksm $ksm) {}

    /** @return array<string, int> kind label => active partners, in the kinds' order, empty kinds left out */
    public function aktifPerJenis(): array
    {
        $jumlah = Mitra::query()->whereBelongsTo($this->ksm)->where('aktif', true)
            ->toBase()->selectRaw('jenis, count(*) as jumlah')->groupBy('jenis')->pluck('jumlah', 'jenis');

        $hasil = [];

        foreach (JenisMitra::cases() as $jenis) {
            if (($jumlah[$jenis->value] ?? 0) > 0) {
                $hasil[$jenis->getLabel()] = (int) $jumlah[$jenis->value];
            }
        }

        return $hasil;
    }

    /**
     * Partners that are active or contributed in the period, with their support and sales.
     *
     * @return list<array{mitra: string, jenis: string, aktif: bool, dukungan: int, jual_kg: float, nilai: int}>
     */
    public function kontribusi(Periode $periode): array
    {
        [$dari, $sampai] = [$periode->dariString(), $periode->sampaiString()];

        $dukungan = TransaksiKas::query()->whereBelongsTo($this->ksm)->where('jenis', JenisTransaksi::Masuk)->whereNotNull('mitra_id')
            ->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)
            ->toBase()->selectRaw('mitra_id, sum(nominal) as jumlah')->groupBy('mitra_id')->pluck('jumlah', 'mitra_id');

        $penjualan = CatatanMaterial::query()->whereBelongsTo($this->ksm)->where('aktivitas', AktivitasMaterial::Jual)->whereNotNull('mitra_id')
            ->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)
            ->toBase()->selectRaw('mitra_id, sum(berat_kg) as kg, sum(nilai_jual) as nilai')->groupBy('mitra_id')->get()->keyBy('mitra_id');

        $berkontribusi = $dukungan->keys()->merge($penjualan->keys())->unique()->values()->all();

        return Mitra::query()
            ->whereBelongsTo($this->ksm)
            ->where(fn (Builder $query) => $query->where('aktif', true)->orWhereIn('id', $berkontribusi))
            ->orderBy('nama')
            ->get()
            ->map(fn (Mitra $mitra): array => [
                'mitra' => $mitra->nama.($mitra->aktif ? '' : ' (nonaktif)'),
                'jenis' => $mitra->jenis->getLabel(),
                'aktif' => $mitra->aktif,
                'dukungan' => (int) ($dukungan[$mitra->getKey()] ?? 0),
                'jual_kg' => round((float) ($penjualan[$mitra->getKey()]->kg ?? 0), 1),
                'nilai' => (int) ($penjualan[$mitra->getKey()]->nilai ?? 0),
            ])
            ->all();
    }

    /** Partners with support or sales in the period. */
    public function jumlahBerkontribusi(Periode $periode): int
    {
        return count(array_filter($this->kontribusi($periode), fn (array $baris): bool => $baris['dukungan'] > 0 || $baris['nilai'] > 0 || $baris['jual_kg'] > 0));
    }

    /** Cash-book income naming any partner in the period. */
    public function dukungan(Periode $periode): int
    {
        return (int) array_sum(array_column($this->kontribusi($periode), 'dukungan'));
    }
}
