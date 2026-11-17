<?php

namespace App\Support\Laporan;

/**
 * One section of a report: title, columns, rows and an optional total row. The screen table,
 * the Excel sheet and the printout are all rendered from the same instance.
 */
final readonly class TabelLaporan
{
    /**
     * @param  list<KolomLaporan>  $kolom
     * @param  list<array<string, scalar|null>>  $baris
     * @param  array<string, scalar|null>|null  $total
     */
    public function __construct(
        public string $judul,
        public array $kolom,
        public array $baris,
        public ?array $total = null,
        public ?string $catatan = null,
    ) {}

    /**
     * The total row, or none when the section has no rows (an all-zero "Jumlah" line would hide
     * the "no data" message and add nothing).
     *
     * @return array<string, scalar|null>|null
     */
    public function totalTampil(): ?array
    {
        return $this->baris === [] ? null : $this->total;
    }

    /**
     * Rows keyed for a Filament custom-data table; the total row is marked "__total".
     *
     * @return array<string, array<string, mixed>>
     */
    public function rekamanTabel(): array
    {
        $rekaman = [];

        foreach ($this->baris as $nomor => $baris) {
            $rekaman[(string) $nomor] = $baris;
        }

        if (($total = $this->totalTampil()) !== null) {
            $rekaman['total'] = [...$total, '__total' => true];
        }

        return $rekaman;
    }
}
