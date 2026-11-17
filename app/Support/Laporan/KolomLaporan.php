<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;

/**
 * One column of a report table. With $formatDariBaris the format comes from each row's
 * "__format" key instead (a summary table mixing rupiah, kg and counts in one column).
 */
final readonly class KolomLaporan
{
    public function __construct(
        public string $kunci,
        public string $label,
        public FormatAngka $format = FormatAngka::Teks,
        public bool $formatDariBaris = false,
    ) {}

    /** @param  array<string, mixed>  $baris */
    public function formatUntuk(array $baris): FormatAngka
    {
        return $this->formatDariBaris && isset($baris['__format'])
            ? FormatAngka::from((string) $baris['__format'])
            : $this->format;
    }
}
