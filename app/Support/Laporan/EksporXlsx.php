<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;
use App\Models\Ksm;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writes a report (its TabelLaporan sections) as an .xlsx download: one sheet per section,
 * numbers as real numbers with a number format — never pre-formatted text. Streamed, so no
 * temporary file is left behind and no queue is needed.
 *
 * Text is always written as a text cell: OpenSpout turns any string starting with "=" into a
 * formula, and names come from users (a petugas edits their own name) — "=HYPERLINK(…)" must
 * stay text, not run when the committee opens the file.
 */
final class EksporXlsx
{
    /** @param  list<TabelLaporan>  $tabel */
    public static function unduh(string $jenis, string $judul, Ksm $ksm, Periode $periode, array $tabel): StreamedResponse
    {
        $namaBerkas = "laporan-{$jenis}-{$ksm->slug}-{$periode->kunci()}.xlsx";

        return response()->streamDownload(function () use ($judul, $ksm, $periode, $tabel): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            self::tulis($writer, $judul, $ksm, $periode, $tabel);
            $writer->close();
        }, $namaBerkas, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @param  list<TabelLaporan>  $tabel */
    private static function tulis(Writer $writer, string $judul, Ksm $ksm, Periode $periode, array $tabel): void
    {
        $tebal = (new Style)->setFontBold();
        $dibuat = CarbonImmutable::now((string) config('app.zona_waktu_lokal'))->translatedFormat('d M Y H:i');
        $namaTerpakai = [];

        foreach (array_values($tabel) as $nomor => $bagian) {
            $sheet = $nomor === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(self::namaSheet($bagian->judul, $namaTerpakai));

            $writer->addRow(self::barisTeks([$ksm->nama], $tebal));
            $writer->addRow(self::barisTeks([$judul.' — '.$bagian->judul]));
            $writer->addRow(self::barisTeks(['Periode: '.$periode->label()]));
            $writer->addRow(self::barisTeks(["Dibuat: {$dibuat}"]));
            $writer->addRow(self::barisTeks([]));
            $writer->addRow(self::barisTeks(array_map(fn (KolomLaporan $kolom): string => $kolom->label, $bagian->kolom), $tebal));

            foreach ($bagian->baris as $baris) {
                $writer->addRow(self::baris($bagian->kolom, $baris, tebal: false));
            }

            if ($bagian->total !== null) {
                $writer->addRow(self::baris($bagian->kolom, $bagian->total, tebal: true));
            }

            if (filled($bagian->catatan)) {
                $writer->addRow(self::barisTeks([]));
                $writer->addRow(self::barisTeks([$bagian->catatan]));
            }
        }
    }

    /**
     * @param  list<KolomLaporan>  $kolom
     * @param  array<string, mixed>  $baris
     */
    private static function baris(array $kolom, array $baris, bool $tebal): Row
    {
        $sel = [];

        foreach ($kolom as $satuKolom) {
            $format = $satuKolom->formatUntuk($baris);
            $style = new Style;

            if ($tebal) {
                $style->setFontBold();
            }

            if ($format->formatExcel() !== null) {
                $style->setFormat($format->formatExcel());
            }

            $sel[] = self::sel(self::nilaiSel($format, $baris[$satuKolom->kunci] ?? null), $style);
        }

        return new Row($sel);
    }

    /** @param  list<string>  $teks */
    private static function barisTeks(array $teks, ?Style $style = null): Row
    {
        return new Row(array_map(fn (string $satu): Cell => self::sel($satu, null), $teks), $style);
    }

    /** Numbers, dates and empties as typed cells; every string as a text cell — never a formula. */
    private static function sel(string|int|float|CarbonImmutable|null $nilai, ?Style $style): Cell
    {
        return is_string($nilai) && $nilai !== '' ? new StringCell($nilai, $style) : Cell::fromValue($nilai, $style);
    }

    private static function nilaiSel(FormatAngka $format, mixed $nilai): string|int|float|CarbonImmutable|null
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        return match ($format) {
            FormatAngka::Teks => (string) $nilai,
            FormatAngka::Rupiah => (int) $nilai,
            FormatAngka::Tanggal => FormatAngka::adalahTanggal($nilai) ? CarbonImmutable::parse((string) $nilai) : (string) $nilai,
            default => is_numeric($nilai) ? $nilai + 0 : (string) $nilai,
        };
    }

    /**
     * Excel sheet names: no []:*?/\ and at most 31 characters, unique within the file.
     *
     * @param  list<string>  $terpakai
     */
    private static function namaSheet(string $judul, array &$terpakai): string
    {
        $dasar = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', '', $judul)) ?: 'Laporan';
        $nama = mb_substr($dasar, 0, 31);

        for ($ke = 2; in_array(mb_strtolower($nama), $terpakai, true); $ke++) {
            $nama = mb_substr($dasar, 0, 31 - mb_strlen(" {$ke}"))." {$ke}";
        }

        $terpakai[] = mb_strtolower($nama);

        return $nama;
    }
}
