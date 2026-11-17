<?php

namespace App\Console\Commands;

use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Load a KSM's paper fee ledger from CSV.
 *
 * Header: nomor_urut,nama,tarif,iuran_bulanan,aktif,catatan,<YYYY-MM>,<YYYY-MM>,...
 * A month cell holds the rupiah amount paid (e.g. 25000); empty means nothing paid.
 * Re-running is safe: customers match on name, payments on (customer, month).
 * The CSV holds residents' personal data — keep it out of git (storage/app/private/).
 */
#[Signature('app:import-iuran
    {ksm : Slug of the KSM, e.g. makmurjaya}
    {file : Path to the CSV}
    {--tarif=* : Tariff to create if missing, "Nama=nominal"}
    {--cek=* : Expected month total from the book, "YYYY-MM=nominal"}
    {--dry-run : Validate and show totals without writing}')]
#[Description("Import a KSM's monthly fee ledger from CSV")]
class ImportIuran extends Command
{
    public function handle(): int
    {
        $ksm = Ksm::query()->firstWhere('slug', $this->argument('ksm'));
        if ($ksm === null) {
            $this->components->error('KSM tidak ditemukan: '.$this->argument('ksm'));

            return self::FAILURE;
        }

        [$bulan, $baris, $masalah] = $this->baca((string) $this->argument('file'));
        $tarif = $this->tarifTersedia($ksm);

        foreach ($baris as $i => $b) {
            if ($b['tarif'] !== '' && ! array_key_exists($b['tarif'], $tarif)) {
                $masalah[] = 'Baris '.($i + 2).": tarif \"{$b['tarif']}\" belum ada (tambahkan --tarif=\"{$b['tarif']}=nominal\")";
            }
        }

        if ($masalah !== []) {
            $this->components->error('CSV tidak diimpor:');
            $this->components->bulletList($masalah);

            return self::FAILURE;
        }

        $this->tampilkanTotal($bulan, $baris);

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run: tidak ada yang ditulis.');

            return $this->cekTotal($bulan, $baris) ? self::SUCCESS : self::FAILURE;
        }

        DB::transaction(function () use ($ksm, $bulan, $baris): void {
            $tarif = $this->buatTarif($ksm);

            foreach ($baris as $b) {
                /** @var Pelanggan $pelanggan */
                $pelanggan = $ksm->pelanggan()->updateOrCreate(['nama' => $b['nama']], [
                    'nomor_urut' => $b['nomor_urut'],
                    'tarif_layanan_id' => $b['tarif'] === '' ? null : $tarif[$b['tarif']],
                    'iuran_bulanan' => $b['iuran_bulanan'],
                    'aktif' => $b['aktif'],
                    'catatan' => $b['catatan'] === '' ? null : $b['catatan'],
                ]);

                foreach ($bulan as $ym) {
                    if ($b['bayar'][$ym] === null) {
                        continue;
                    }

                    $periode = Carbon::createFromFormat('!Y-m', $ym);
                    $pembayaran = PembayaranIuran::query()
                        ->whereBelongsTo($pelanggan)
                        ->whereDate('periode', $periode->toDateString())
                        ->first() ?? new PembayaranIuran(['pelanggan_id' => $pelanggan->id, 'periode' => $periode]);

                    $pembayaran->nominal = $b['bayar'][$ym];
                    $pembayaran->save();
                }
            }
        });

        $this->components->info(count($baris)." pelanggan diimpor ke {$ksm->nama}.");

        return $this->cekTotal($bulan, $baris) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0: list<string>, 1: list<array{nomor_urut: ?int, nama: string, tarif: string, iuran_bulanan: int, aktif: bool, catatan: string, bayar: array<string, ?int>}>, 2: list<string>}
     */
    private function baca(string $path): array
    {
        if (! is_readable($path) || ($handle = fopen($path, 'r')) === false) {
            return [[], [], ["Berkas tidak bisa dibaca: {$path}"]];
        }

        $header = array_map('trim', fgetcsv($handle, escape: '') ?: []);
        $wajib = ['nomor_urut', 'nama', 'tarif', 'iuran_bulanan', 'aktif', 'catatan'];
        $kurang = array_diff($wajib, $header);
        $bulan = array_values(array_filter($header, fn (string $h): bool => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $h) === 1));

        if ($kurang !== [] || $bulan === []) {
            fclose($handle);

            return [[], [], ['Header harus memuat '.implode(',', $wajib).' dan minimal satu kolom bulan YYYY-MM.']];
        }

        $baris = [];
        $masalah = [];
        $nomor = 1;

        while (($kolom = fgetcsv($handle, escape: '')) !== false) {
            $nomor++;
            if ($kolom === [null] || trim(implode('', $kolom)) === '') {
                continue;
            }

            $data = array_combine($header, array_pad(array_map('trim', $kolom), count($header), ''));

            if ($data['nama'] === '' || ! ctype_digit($data['iuran_bulanan'])) {
                $masalah[] = "Baris {$nomor}: nama kosong atau iuran_bulanan bukan angka.";

                continue;
            }

            $bayar = [];
            foreach ($bulan as $ym) {
                $sel = $data[$ym];
                if ($sel !== '' && ! ctype_digit($sel)) {
                    $masalah[] = "Baris {$nomor} ({$data['nama']}), {$ym}: \"{$sel}\" bukan angka rupiah.";
                }
                $bayar[$ym] = ($sel === '' || $sel === '0') ? null : (int) $sel;
            }

            $baris[] = [
                'nomor_urut' => ctype_digit($data['nomor_urut']) ? (int) $data['nomor_urut'] : null,
                'nama' => $data['nama'],
                'tarif' => $data['tarif'],
                'iuran_bulanan' => (int) $data['iuran_bulanan'],
                'aktif' => ! in_array(strtolower($data['aktif']), ['0', 'tidak', 'no', 'false'], true),
                'catatan' => $data['catatan'],
                'bayar' => $bayar,
            ];
        }

        fclose($handle);

        return [$bulan, $baris, $masalah];
    }

    /** @return array<string, int> nama => id, including tariffs about to be created */
    private function tarifTersedia(Ksm $ksm): array
    {
        $ada = $ksm->tarifLayanan()->pluck('id', 'nama')->all();

        foreach ($this->tarifDariOpsi() as $nama => $nominal) {
            $ada[$nama] ??= 0;
        }

        return $ada;
    }

    /** @return array<string, int> nama => id */
    private function buatTarif(Ksm $ksm): array
    {
        foreach ($this->tarifDariOpsi() as $nama => $nominal) {
            $ksm->tarifLayanan()->firstOrCreate(['nama' => $nama], ['nominal' => $nominal]);
        }

        return $ksm->tarifLayanan()->pluck('id', 'nama')->all();
    }

    /** @return array<string, int> */
    private function tarifDariOpsi(): array
    {
        $tarif = [];
        foreach ((array) $this->option('tarif') as $opsi) {
            [$nama, $nominal] = array_pad(explode('=', (string) $opsi, 2), 2, '');
            if (trim($nama) !== '' && ctype_digit(trim($nominal))) {
                $tarif[trim($nama)] = (int) trim($nominal);
            }
        }

        return $tarif;
    }

    /**
     * @param  list<string>  $bulan
     * @param  list<array{bayar: array<string, ?int>, aktif: bool}>  $baris
     */
    private function tampilkanTotal(array $bulan, array $baris): void
    {
        $this->table(
            ['Bulan', 'Total', 'Yang bayar'],
            array_map(fn (string $ym): array => [
                $ym,
                'Rp'.number_format($this->total($ym, $baris), 0, ',', '.'),
                count(array_filter($baris, fn (array $b): bool => $b['bayar'][$ym] !== null)),
            ], $bulan),
        );
        $this->line(count($baris).' pelanggan, '.count(array_filter($baris, fn (array $b): bool => $b['aktif'])).' aktif.');
    }

    /**
     * Compare against the totals written at the bottom of the paper ledger.
     *
     * @param  list<string>  $bulan
     * @param  list<array{bayar: array<string, ?int>}>  $baris
     */
    private function cekTotal(array $bulan, array $baris): bool
    {
        $cocok = true;

        foreach ((array) $this->option('cek') as $opsi) {
            [$ym, $harapan] = array_pad(explode('=', (string) $opsi, 2), 2, '');
            if (! in_array($ym, $bulan, true)) {
                continue;
            }

            $hitung = $this->total($ym, $baris);
            if ($hitung === (int) $harapan) {
                $this->components->twoColumnDetail("{$ym} cocok dengan buku", 'Rp'.number_format($hitung, 0, ',', '.'));
            } else {
                $cocok = false;
                $this->components->twoColumnDetail(
                    "<fg=red>{$ym} BEDA dengan buku</>",
                    'CSV Rp'.number_format($hitung, 0, ',', '.').' · buku Rp'.number_format((int) $harapan, 0, ',', '.'),
                );
            }
        }

        return $cocok;
    }

    /** @param  list<array{bayar: array<string, ?int>}>  $baris */
    private function total(string $ym, array $baris): int
    {
        return array_sum(array_map(fn (array $b): int => (int) $b['bayar'][$ym], $baris));
    }
}
