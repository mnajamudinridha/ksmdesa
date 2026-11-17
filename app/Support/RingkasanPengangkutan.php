<?php

namespace App\Support;

use App\Enums\StatusPengangkutan;
use App\Models\Ksm;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Collection figures for the dashboards: a whole KSM, or one petugas within it.
 * Days are village calendar days (Y-m-d from App\Support\Hari).
 */
final class RingkasanPengangkutan
{
    public function __construct(private readonly Ksm $ksm, private readonly ?User $petugas = null) {}

    public function rumahDiangkut(string $tanggal): int
    {
        return $this->rincian($tanggal, $tanggal)->where('status', StatusPengangkutan::Diangkut)->count();
    }

    public function rumahDicatat(string $tanggal): int
    {
        return $this->rincian($tanggal, $tanggal)->count();
    }

    public function berat(string $dari, string $sampai): float
    {
        return (float) $this->rincian($dari, $sampai)->sum('perkiraan_kg');
    }

    /**
     * Estimated kg per day for the last $jumlahHari days up to today, oldest first, days
     * without a round as 0.
     *
     * @return array<string, float>
     */
    public function beratPerHari(int $jumlahHari): array
    {
        $dari = Hari::tanggal($jumlahHari - 1);

        $tercatat = $this->ronde()
            ->join('pengangkutan_rincian', 'pengangkutan_rincian.pengangkutan_id', '=', 'pengangkutan.id')
            ->whereDate('pengangkutan.tanggal', '>=', $dari)
            ->selectRaw('date(pengangkutan.tanggal) as hari, sum(pengangkutan_rincian.perkiraan_kg) as kg')
            ->groupBy('hari')
            ->pluck('kg', 'hari');

        $hasil = [];

        for ($mundur = $jumlahHari - 1; $mundur >= 0; $mundur--) {
            $hari = Hari::tanggal($mundur);
            $hasil[$hari] = round((float) ($tercatat[$hari] ?? 0), 1);
        }

        return $hasil;
    }

    public static function kg(float $kg): string
    {
        return Number::format($kg, precision: 1, locale: 'id').' kg';
    }

    /** @return Builder<Pengangkutan> */
    private function ronde(): Builder
    {
        return Pengangkutan::query()
            ->whereBelongsTo($this->ksm)
            ->when($this->petugas, fn (Builder $query) => $query->where('pengangkutan.petugas_id', $this->petugas->getKey()));
    }

    /** @return Builder<PengangkutanRincian> */
    private function rincian(string $dari, string $sampai): Builder
    {
        return PengangkutanRincian::query()->whereIn('pengangkutan_id', $this->ronde()
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->select('pengangkutan.id'));
    }
}
