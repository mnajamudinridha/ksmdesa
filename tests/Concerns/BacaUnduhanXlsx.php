<?php

namespace Tests\Concerns;

use Livewire\Features\SupportTesting\Testable;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

/**
 * Reads the .xlsx a Livewire action just downloaded, so a test can compare the spreadsheet's
 * cells with what the screen shows.
 */
trait BacaUnduhanXlsx
{
    /**
     * @return array<string, list<list<mixed>>> sheet name => rows of cell values
     */
    protected function bacaUnduhanXlsx(Testable $komponen): array
    {
        $berkas = tempnam(sys_get_temp_dir(), 'ksm-xlsx-');
        file_put_contents($berkas, base64_decode($komponen->effects['download']['content']));

        $reader = new Reader;
        $reader->open($berkas);
        $isi = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $isi[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();
        unlink($berkas);

        return $isi;
    }

    /**
     * Raw XML of every worksheet in the download — to check how cells are stored (e.g. that
     * no cell is a formula, which a reader would hand back as plain text).
     *
     * @return array<string, string> zip entry name => XML
     */
    protected function xmlLembarXlsx(Testable $komponen): array
    {
        $berkas = tempnam(sys_get_temp_dir(), 'ksm-xlsx-');
        file_put_contents($berkas, base64_decode($komponen->effects['download']['content']));

        $zip = new ZipArchive;
        $zip->open($berkas);
        $xml = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = (string) $zip->getNameIndex($i);

            if (str_starts_with($nama, 'xl/worksheets/sheet')) {
                $xml[$nama] = (string) $zip->getFromName($nama);
            }
        }

        $zip->close();
        unlink($berkas);

        return $xml;
    }

    /**
     * The row whose first cell is $label.
     *
     * @param  list<list<mixed>>  $baris
     * @return list<mixed>
     */
    protected function barisBerlabel(array $baris, string $label): array
    {
        foreach ($baris as $satu) {
            if (($satu[0] ?? null) === $label) {
                return $satu;
            }
        }

        $this->fail("Tidak ada baris berlabel {$label}.");
    }
}
