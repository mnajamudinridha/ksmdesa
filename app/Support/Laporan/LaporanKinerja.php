<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;
use App\Models\Ksm;
use App\Support\Periode;
use App\Support\RingkasanKinerja;

/** Integrated performance report: the circular-economy indicators and a summary across areas. */
final class LaporanKinerja
{
    /** @var array<string, array{0: string, 1: string}> indicator key => [label, how it is measured] */
    public const array INDIKATOR = [
        'penagihan' => ['Tingkat penagihan iuran', 'iuran terkumpul ÷ target iuran'],
        'cakupan' => ['Cakupan layanan', 'rumah aktif diangkut ≥1× ÷ rumah aktif'],
        'pemulihan' => ['Tingkat pemulihan material', 'material terpulihkan ÷ sampah terangkut'],
        'kemandirian' => ['Kemandirian biaya', '(iuran + penjualan material) ÷ pengeluaran'],
    ];

    private readonly RingkasanKinerja $kinerja;

    public function __construct(Ksm $ksm)
    {
        $this->kinerja = new RingkasanKinerja($ksm);
    }

    /** @return list<TabelLaporan> */
    public function semua(Periode $periode): array
    {
        return [$this->indikator($periode), $this->ringkasan($periode)];
    }

    /** @return list<KolomLaporan> */
    public static function kolomIndikator(): array
    {
        return [
            new KolomLaporan('indikator', 'Indikator'),
            new KolomLaporan('rumus', 'Rumus'),
            new KolomLaporan('nilai', 'Periode ini', FormatAngka::Persen),
            new KolomLaporan('sebelumnya', 'Periode sebelumnya', FormatAngka::Persen),
            new KolomLaporan('perubahan', 'Perubahan (poin)', FormatAngka::Angka),
        ];
    }

    public function indikator(Periode $periode): TabelLaporan
    {
        $ini = $this->kinerja->indikator($periode);
        $sebelumnya = $this->kinerja->indikator($periode->sebelumnya());

        $baris = [];

        foreach (self::INDIKATOR as $kunci => [$label, $rumus]) {
            $baris[] = [
                'indikator' => $label,
                'rumus' => $rumus,
                'nilai' => $ini[$kunci],
                'sebelumnya' => $sebelumnya[$kunci],
                'perubahan' => $ini[$kunci] === null || $sebelumnya[$kunci] === null ? null : round(($ini[$kunci] - $sebelumnya[$kunci]) * 100, 1),
            ];
        }

        return new TabelLaporan(
            judul: 'Indikator ekonomi sirkular',
            kolom: self::kolomIndikator(),
            baris: $baris,
            catatan: 'Periode sebelumnya: '.$periode->sebelumnya()->label().'. "—" = belum ada data untuk dihitung.',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomRingkasan(): array
    {
        return [
            new KolomLaporan('bidang', 'Bidang'),
            new KolomLaporan('indikator', 'Indikator'),
            new KolomLaporan('nilai', 'Periode ini', FormatAngka::Angka, formatDariBaris: true),
            new KolomLaporan('sebelumnya', 'Periode sebelumnya', FormatAngka::Angka, formatDariBaris: true),
            new KolomLaporan('perubahan', 'Perubahan', FormatAngka::Persen),
        ];
    }

    public function ringkasan(Periode $periode): TabelLaporan
    {
        return new TabelLaporan(
            judul: 'Ringkasan kinerja',
            kolom: self::kolomRingkasan(),
            baris: $this->kinerja->ringkasan($periode),
            catatan: 'Perubahan = selisih terhadap '.$periode->sebelumnya()->label().' dibanding nilainya ("—" bila sebelumnya nol).',
        );
    }
}
