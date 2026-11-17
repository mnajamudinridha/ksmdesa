<?php

namespace Tests\Unit;

use App\Enums\StatusIuran;
use App\Support\SubdomainSlug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubdomainSlugTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function slugs(): array
    {
        return [
            'village name' => ['makmurjaya', true],
            'inner hyphen' => ['makmur-jaya', true],
            'digits' => ['desa2', true],
            'uppercase' => ['MakmurJaya', false],
            'leading hyphen' => ['-makmur', false],
            'trailing hyphen' => ['makmur-', false],
            'dot' => ['makmur.jaya', false],
            'space' => ['makmur jaya', false],
            '64 chars' => [str_repeat('a', 64), false],
            'reserved www' => ['www', false],
            'reserved mail' => ['mail', false],
            'reserved healthcheck' => ['deploy-healthcheck', false],
        ];
    }

    #[DataProvider('slugs')]
    public function test_slug_rules(string $slug, bool $boleh): void
    {
        $this->assertSame($boleh, SubdomainSlug::problem($slug) === null);
    }

    public function test_unambiguous_payment_statuses(): void
    {
        $this->assertSame(StatusIuran::Belum, StatusIuran::tentukan(true, 25_000, null));
        $this->assertSame(StatusIuran::Lunas, StatusIuran::tentukan(true, 25_000, 25_000));
        $this->assertSame(StatusIuran::Nonaktif, StatusIuran::tentukan(false, 25_000, null));
        $this->assertTrue(StatusIuran::Belum->perluDitagih());
        $this->assertFalse(StatusIuran::Lunas->perluDitagih());
    }
}
