<?php

namespace App\Filament\Ksm\Pages;

use App\Enums\FormatAngka;
use App\Enums\StatusIuran;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Support\Laporan\EksporXlsx;
use App\Support\Laporan\KolomLaporan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The paper ledger as a screen: one row per customer, one column per month,
 * a total per month at the bottom — the same shape the KSM already keeps by hand.
 */
class RekapIuran extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $title = 'Rekap Iuran';

    protected static ?int $navigationSort = 10;

    #[Url]
    public ?int $tahun = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function mount(): void
    {
        $this->tahun ??= $this->tahunTersedia()[0];
    }

    public function getSubheading(): string
    {
        return "Tahun {$this->tahun} · warna: hijau lunas, kuning kurang, biru lebih, merah belum bayar, abu-abu nonaktif.";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(array_map(
                fn (int $tahun): Action => Action::make("tahun{$tahun}")
                    ->label((string) $tahun)
                    ->url(static::getUrl(['tahun' => $tahun])),
                $this->tahunTersedia(),
            ))
                ->label("Tahun {$this->tahun}")
                ->icon(Heroicon::OutlinedCalendar)
                ->button(),
            Action::make('unduhExcel')
                ->label('Unduh Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->authorize(fn (): bool => (bool) auth()->user()?->isAdminKsm())
                ->action(fn (): StreamedResponse => EksporXlsx::unduh(
                    'iuran',
                    'Rekap Iuran',
                    Filament::getTenant(),
                    Periode::dariFilter(['tahun' => $this->tahun, 'bulan' => Periode::SETAHUN]),
                    [$this->tabelRekap()],
                )),
        ];
    }

    /**
     * The ledger as on screen — the same customers (the table's filters applied), months and
     * amounts — for the Excel download.
     */
    public function tabelRekap(): TabelLaporan
    {
        $bulan = $this->bulanDitampilkan();
        $kolomBulan = array_map(fn (Carbon $satu): KolomLaporan => new KolomLaporan('bulan_'.$satu->format('Y-m'), $satu->translatedFormat('M Y'), FormatAngka::Rupiah), $bulan);

        $baris = $this->getFilteredTableQuery()
            ->reorder()
            ->orderBy('nomor_urut')
            ->orderBy('nama')
            ->get()
            ->map(function (Pelanggan $pelanggan) use ($bulan): array {
                $dibayar = $this->pembayaran[$pelanggan->id] ?? [];
                $perBulan = [];

                foreach ($bulan as $satu) {
                    $perBulan['bulan_'.$satu->format('Y-m')] = $dibayar[$satu->format('Y-m')] ?? null;
                }

                return [
                    'nomor_urut' => $pelanggan->nomor_urut,
                    'nama' => $pelanggan->nama.($pelanggan->aktif ? '' : ' (nonaktif)'),
                    'iuran_bulanan' => $pelanggan->iuran_bulanan,
                    ...$perBulan,
                    'total' => array_sum($dibayar),
                ];
            })
            ->all();

        $total = ['nomor_urut' => null, 'nama' => 'Jumlah', 'iuran_bulanan' => null];

        foreach ([...array_map(fn (KolomLaporan $kolom): string => $kolom->kunci, $kolomBulan), 'total'] as $kunci) {
            $total[$kunci] = (int) array_sum(array_column($baris, $kunci));
        }

        return new TabelLaporan(
            judul: "Rekap Iuran {$this->tahun}",
            kolom: [
                new KolomLaporan('nomor_urut', 'No.', FormatAngka::Angka),
                new KolomLaporan('nama', 'Nama'),
                new KolomLaporan('iuran_bulanan', 'Iuran/bln', FormatAngka::Rupiah),
                ...$kolomBulan,
                new KolomLaporan('total', 'Total', FormatAngka::Rupiah),
            ],
            baris: $baris,
            total: $total,
            catatan: 'Per bulan iuran (bukan tanggal diterima). Sel kosong = belum ada pembayaran tercatat.',
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Pelanggan::query()
                ->whereBelongsTo(Filament::getTenant())
                ->with('tarifLayanan'))
            ->defaultSort('nomor_urut')
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('nomor_urut')
                    ->label('No.')
                    ->sortable(),
                TextColumn::make('nama')
                    ->description(fn (Pelanggan $record): string => 'Rp'.number_format($record->iuran_bulanan, 0, ',', '.').'/bln'
                        .($record->aktif ? '' : ' · nonaktif'))
                    ->searchable()
                    ->sortable(),
                ...array_map(fn (Carbon $bulan): TextColumn => $this->kolomBulan($bulan), $this->bulanDitampilkan()),
                TextColumn::make('total_tahun')
                    ->label('Total')
                    ->state(fn (Pelanggan $record): int => array_sum($this->pembayaran[$record->id] ?? []))
                    ->formatStateUsing(fn (int $state): string => self::rupiah($state))
                    ->weight('bold')
                    ->alignEnd()
                    ->summarize(Summarizer::make()
                        ->using(fn (Builder $query): int => (int) $this->pembayaranTahunIni($query)->sum('nominal'))
                        ->formatStateUsing(fn ($state): string => self::rupiah((int) $state))),
            ])
            ->filters([
                TernaryFilter::make('aktif'),
            ]);
    }

    private function kolomBulan(Carbon $bulan): TextColumn
    {
        $kunci = $bulan->format('Y-m');

        return TextColumn::make("bulan_{$kunci}")
            ->label($bulan->translatedFormat('M'))
            ->state(fn (Pelanggan $record): ?int => $this->pembayaran[$record->id][$kunci] ?? null)
            ->formatStateUsing(fn (?int $state): string => self::rupiah($state))
            ->placeholder('–')
            ->color(fn (Pelanggan $record): string => $this->status($record, $kunci)->getColor())
            ->tooltip(fn (Pelanggan $record): string => $this->status($record, $kunci)->getLabel())
            ->alignEnd()
            ->summarize(Summarizer::make()
                ->using(fn (Builder $query): int => (int) $this->pembayaranTahunIni($query)
                    ->whereDate('periode', $bulan->toDateString())
                    ->sum('nominal'))
                ->formatStateUsing(fn ($state): string => self::rupiah((int) $state)));
    }

    private function status(Pelanggan $pelanggan, string $kunci): StatusIuran
    {
        return StatusIuran::tentukan($pelanggan->aktif, $pelanggan->iuran_bulanan, $this->pembayaran[$pelanggan->id][$kunci] ?? null);
    }

    /**
     * Payments of the selected year for the customers in the (filtered) table query.
     *
     * @return \Illuminate\Database\Eloquent\Builder<PembayaranIuran>
     */
    private function pembayaranTahunIni(Builder $pelangganQuery)
    {
        return PembayaranIuran::query()
            ->whereBelongsTo(Filament::getTenant())
            ->whereIn('pelanggan_id', (clone $pelangganQuery)->pluck('id'))
            ->whereYear('periode', $this->tahun);
    }

    /**
     * pelanggan_id => ['Y-m' => nominal] for the selected year, loaded once per request.
     *
     * @return array<int, array<string, int>>
     */
    #[Computed]
    public function pembayaran(): array
    {
        $hasil = [];

        PembayaranIuran::query()
            ->whereBelongsTo(Filament::getTenant())
            ->whereYear('periode', $this->tahun)
            ->get(['pelanggan_id', 'periode', 'nominal'])
            ->each(function (PembayaranIuran $p) use (&$hasil): void {
                $hasil[$p->pelanggan_id][$p->periode->format('Y-m')] = $p->nominal;
            });

        return $hasil;
    }

    /**
     * From the first recorded month of the year to the last one — or to this month,
     * so unrecorded recent months show up as gaps instead of silently disappearing.
     *
     * @return list<Carbon>
     */
    private function bulanDitampilkan(): array
    {
        $periode = PembayaranIuran::query()
            ->whereBelongsTo(Filament::getTenant())
            ->whereYear('periode', $this->tahun)
            ->selectRaw('MIN(periode) AS awal, MAX(periode) AS akhir')
            ->first();

        $awal = $periode?->awal ? Carbon::parse($periode->awal) : Carbon::create($this->tahun, 1);
        $akhir = $periode?->akhir ? Carbon::parse($periode->akhir) : $awal->copy();

        if ($this->tahun === now()->year && now()->startOfMonth()->greaterThan($akhir)) {
            $akhir = now()->startOfMonth();
        }

        $bulan = [];
        for ($b = $awal->copy()->startOfMonth(); $b->lessThanOrEqualTo($akhir); $b->addMonth()) {
            $bulan[] = $b->copy();
        }

        return $bulan;
    }

    /**
     * Years that have payments, newest first; the current year is always offered.
     *
     * @return non-empty-list<int>
     */
    private function tahunTersedia(): array
    {
        return PembayaranIuran::query()
            ->whereBelongsTo(Filament::getTenant())
            ->pluck('periode')
            ->map(fn (Carbon $periode): int => $periode->year)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private static function rupiah(?int $nominal): string
    {
        return 'Rp'.number_format((int) $nominal, 0, ',', '.');
    }
}
