<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMXPath;
use Livewire\Features\SupportTesting\Testable;

/**
 * Reads a stats-overview widget's cards as label => value, so a test can assert one card's
 * exact value (assertSeeInOrder would also match a "2" anywhere later in the HTML).
 */
trait BacaNilaiStat
{
    /** @return array<string, string> */
    protected function nilaiStat(Testable $widget): array
    {
        $dokumen = new DOMDocument;
        @$dokumen->loadHTML('<?xml encoding="utf-8"?>'.$widget->html());
        $xpath = new DOMXPath($dokumen);
        $nilai = [];

        foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' fi-wi-stats-overview-stat ')]") as $kartu) {
            $label = $xpath->query(".//*[contains(@class, 'fi-wi-stats-overview-stat-label')][not(contains(@class, '-ctn'))]", $kartu)->item(0);
            $isi = $xpath->query(".//*[contains(@class, 'fi-wi-stats-overview-stat-value')]", $kartu)->item(0);

            if ($label !== null) {
                $nilai[trim($label->textContent)] = trim((string) $isi?->textContent);
            }
        }

        return $nilai;
    }
}
