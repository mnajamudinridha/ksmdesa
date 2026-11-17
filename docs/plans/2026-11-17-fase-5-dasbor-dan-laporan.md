# Blueprint Fase 5: Dasbor analitik & laporan (mockup "KSM Circular Economy Dashboard")

Status: **DISETUJUI (2026-11-17). Fase 5A DIIMPLEMENTASIKAN; 5B dan 5C menyusul.** Catatan implementasi 5A:
migrasi wilayah **aditif** (kolom `rt`/`rw` lama dibiarkan, tidak dihapus); `HalamanLaporan` memakai `abstract tabelLaporan(Periode): list<TabelLaporan>` (bukan `pembangun()`); `rtTerlayani()` menerima
baris `perWilayah()` (tidak menghitung ulang); aturan cetak = A4 **lanskap**, warna dipaksa gelap-di-putih (mode gelap),
judul layar disembunyikan (kop sudah memuatnya); `TabelLaporanWidget::$isLazy = false` agar cetak tidak menangkap tabel yang
masih memuat; lapisan filter KSM eksplisit diuji dengan berpindah ke panel master (scope tenant Filament hanya berjalan di
panel `ksm`); nilai kartu Stat diuji lewat `Tests\Concerns\BacaNilaiStat`. Audit keamanan (skill `filament-security-audit`): 1 temuan —
injeksi rumus Excel (teks berawalan `=` ditulis OpenSpout sebagai rumus) — diperbaiki dengan sel teks eksplisit. 20 uji sabotase merah sesuai harapan. Lanjutan dari Fase 1–3
(`2026-11-17-peran-pengguna-dan-pengangkutan.md`) dan Fase 4 (`2026-11-17-fase-4-petugas-catat-iuran.md`);
semua aturan kanonik di sana (`App\Support\Hari`, `NomorHp`, tenancy satu-DB, `diKsmLain`, uji sabotase,
`StatusIuran`, status setor) tetap berlaku. Disusun dengan skill `planning-filament` (Filament Blueprint v2.4).

Versi terpasang: Laravel 13.42.0 · Filament 5.11.4 · Livewire 4.4.8 · PHPUnit 12.5 (bukan Pest) · OpenSpout 4.32.0
(sudah terpasang sebagai dependensi `filament/actions`).

Sumber verifikasi: **Boost `search-docs` tersedia** (dasbor ganda, filter halaman, tabel data kustom, filter
chart) **+ kode sumber terpasang** (baris disebut di tiap bagian). Yang masih harus dicek saat implementasi
ditandai **[cek]** dan tidak mengubah rencana bila hasilnya berbeda (alternatifnya sudah ditulis).

Data produksi saat rencana dibuat (cermin lokal): 1 KSM, 38 pelanggan — **RT/RW semuanya kosong**;
167 pembayaran iuran April–Agustus 2026 (Rp6.390.000) — **semuanya tanpa `dibayar_pada`** (hasil impor);
0 pengangkutan, 0 setoran.

---

## 0. Keputusan

### 0.1 Keputusan pemilik (2026-11-17)

| Topik | Keputusan |
|---|---|
| Buku kas | **Buku kas lengkap.** Admin mencatat pemasukan lain & pengeluaran (tanggal, kategori dari Data Master, nominal, uraian, foto/PDF bukti) + saldo awal KSM. **Iuran dan penjualan material masuk otomatis** — dihitung dari sumbernya, tidak diketik ulang. **Tanpa anggaran.** |
| Tanggal kas iuran | **Tanggal diterima** (`dibayar_pada`); bila kosong (167 baris impor) → tanggal 1 bulan iurannya (`periode`). Bayar 3 bulan sekaligus = pemasukan bulan pembayaran. **Rekap Iuran tetap per bulan iuran.** |
| Material (lingkungan) | **Catatan material tingkat KSM**, satu catatan per kegiatan **pilah / jual / olah**: tanggal, jenis material (Data Master), berat kg, pengepul (jual), nilai jual (jual), hasil olah (olah, opsional). Petugas tetap hanya mengisi perkiraan kg total per rumah. |
| Material terpulihkan | **Pilah + olah.** Jual = penyaluran dari hasil pilah (kg & Rp terjual), tidak dihitung dua kali. Stok perkiraan = pilah − jual per jenis. |
| Kemitraan | **Daftar mitra saja** (tanpa program/target/realisasi), **tertaut ke kas & pengepul**: pemasukan buku kas boleh memilih mitra (→ nilai dukungan per mitra); pengepul di catatan jual dipilih dari mitra berjenis Pengepul. |
| RT/wilayah | **Master RT/RW** (+ jumlah KK opsional untuk cakupan layanan); form Pelanggan memilih dari daftar. RT lama kosong → tidak ada data yang dipindahkan. |
| Realisasi per RT | **Cakupan, tanpa jadwal:** % rumah aktif yang diangkut ≥1× dalam periode + rata-rata kunjungan per rumah; biaya operasional = pengeluaran buku kas. Tanpa tabel jadwal. |
| Progres ekonomi sirkular | Empat indikator: **tingkat penagihan iuran, cakupan layanan, tingkat pemulihan material, kemandirian biaya** (rumus §3.6). |
| Ekspor | **Unduh Excel (.xlsx) langsung** (OpenSpout, dicantumkan eksplisit di `composer.json`) **+ tampilan cetak** (browser → "Simpan sebagai PDF"). Tanpa paket baru, tanpa antrean, tanpa tabel `exports`. |

### 0.2 Keputusan turunan yang diusulkan (disetujui bersama rencana; ubah bila tidak sesuai)

| Topik | Usulan |
|---|---|
| Siapa mencatat & melihat | **Admin KSM (dan administrator pusat) saja**: buku kas, catatan material, mitra, semua Data Master baru, halaman Analitik & Laporan. Petugas & warga tidak melihat menu/halaman baru; **dasbor petugas dan warga tidak berubah**. |
| Saldo awal | Dua isian di Profil KSM (bagian "Buku kas"): **Saldo kas awal** (Rp) dan **Mulai pembukuan** (tanggal, opsional). Saldo dihitung sejak tanggal itu; arus kas sebelum tanggal itu tetap tampil di laporan, tetapi kolom saldonya "—". Kosong = sejak catatan pertama. Alasan: pengeluaran April–November belum pernah dicatat; tanpa tanggal mulai, saldo akan terlalu besar. |
| Hapus data master | Wilayah, Kategori Transaksi, Jenis Material, Mitra: **hapus hanya bila belum dipakai**; yang sudah dipakai dinonaktifkan (Wilayah tidak punya status nonaktif: tidak bisa dihapus selama masih ada pelanggan). FK ke master memakai **`nullOnDelete`**, bukan restrict — menghapus KSM meng-cascade semua tabelnya dan restrict antar-tabel anak membuatnya gagal (jebakan README §3). Penjaga utama = kebijakan. |
| Ubah/hapus transaksi | Admin boleh mengubah & menghapus transaksi kas dan catatan material kapan saja (tidak ada tutup buku). `dicatat_oleh_id` disimpan untuk jejak. |
| Kategori sistem | "Iuran" dan "Penjualan material" **bukan** baris kategori — label tetap di laporan. Nama kategori buatan admin yang sama dengan keduanya ditolak (mencegah iuran tercatat dua kali). |
| Jenis transaksi tetap | `jenis` (masuk/keluar) disimpan di transaksi **dan** di kategori; kategori yang sudah dipakai tidak bisa diganti jenisnya (field nonaktif). |
| Bukti | Foto (JPG/PNG/WebP) atau PDF ≤ 4 MB di disk privat `local` (`bukti-kas/`), dibuka lewat URL bertanda tangan 30 menit (pola avatar); berkas lama dihapus saat diganti/transaksi dihapus. |
| Filter periode | **Tahun + bulan** (atau "Setahun penuh"); default bulan berjalan (hari desa). |
| KSM "aktif mencatat" (master) | Ada catatan baru (pengangkutan, pembayaran iuran, setoran; +transaksi kas 5B; +catatan material 5C) **dalam 7 hari terakhir** (`created_at`). |
| Nilai dukungan mitra | Σ transaksi kas **masuk** yang memilih mitra itu (kategori apa pun). Penjualan ke pengepul dihitung terpisah sebagai "penjualan", bukan dukungan. |
| Biaya operasional | Total pengeluaran buku kas pada periode (tidak dirinci per RT). |
| Warna indikator | ≥ 80 % hijau, 50–79 % kuning, < 50 % merah; kemandirian biaya ≥ 100 % hijau. Kosmetik. |
| Kop & tanda tangan cetak | Kop (logo, nama KSM, desa, judul, periode, tanggal cetak) dan kolom tanda tangan kosong "Ketua" / "Bendahara" hanya saat dicetak. |

### 0.3 Di luar cakupan
Anggaran; program/kegiatan kemitraan dengan target & realisasi; jadwal pengangkutan terencana; material per
rumah; tutup buku/kunci periode; PDF server-side; ekspor ber-antrean Filament (`ExportAction`); notifikasi;
petugas mencatat kas/material; dasbor warga/petugas baru; widget lintas-KSM untuk keuangan/material.

---

## 1. Pembagian fase (masing-masing bisa disetujui, dikerjakan, dan di-deploy sendiri)

| Fase | Isi | Bergantung pada |
|---|---|---|
| **5A — Fondasi & data sekarang** | Master **Wilayah (RT/RW)** + `pelanggan.wilayah_id`; Dasbor desa dengan daftar widget eksplisit + **Kelengkapan data**; halaman **Analitik › Operasional**; infrastruktur laporan (`Periode`, `TabelLaporan`, Excel, cetak); **Laporan › Operasional**; **Unduh Excel** di Rekap Iuran; **widget panel master** lintas-KSM. | — |
| **5B — Buku kas & mitra** | `KategoriTransaksi`, `TransaksiKas` ("Buku Kas"), `Mitra`, saldo awal di Profil KSM; `RingkasanKas`; **Analitik › Keuangan**; **Laporan › Keuangan**; kartu keuangan di Dasbor. | 5A |
| **5C — Lingkungan, kemitraan & kinerja** | `JenisMaterial`, `CatatanMaterial`; `RingkasanMaterial`; penjualan material masuk kas; **Analitik › Lingkungan**, **Analitik › Kemitraan**; **Laporan › Lingkungan / Kemitraan / Kinerja terintegrasi**; kartu **Progres ekonomi sirkular**, Lingkungan & Kemitraan di Dasbor. | 5A, 5B |

### 1.1 Peta mockup → aplikasi (panel desa, admin)

| Mockup | Aplikasi | Fase |
|---|---|---|
| Dashboard Overview | **Dasbor** (`/`) — kartu keuangan, progres ekonomi sirkular, iuran, operasional, lingkungan, kemitraan, tren kas, komposisi pemasukan, kelengkapan data | 5A → 5C bertahap |
| Financial Analytics | **Analitik › Keuangan** (`/analitik/keuangan`) | 5B |
| Operational Analytics | **Analitik › Operasional** (`/analitik/operasional`) | 5A |
| Environmental Analytics | **Analitik › Lingkungan** (`/analitik/lingkungan`) | 5C |
| Partnership Analytics | **Analitik › Kemitraan** (`/analitik/kemitraan`) | 5C |
| Data Input & Administration | grup navigasi **Data Input** (Pembayaran Iuran, Pengangkutan, Setoran, **Buku Kas**, **Catatan Material**) + **Data Master** (Pengguna, Pelanggan, Tarif, **Wilayah**, **Kategori Transaksi**, **Jenis Material**, **Mitra**, Profil KSM) + widget **Kelengkapan data** | 5A–5C |
| Reports & Evaluation | grup **Laporan**: Rekap Iuran (+Excel), **Keuangan**, **Operasional**, **Lingkungan**, **Kemitraan**, **Kinerja** | 5A–5C |

Grup navigasi panel desa (urutan = sidebar mockup): `['Analitik', 'Data Input', 'Laporan', 'Data Master']`.

### 1.2 Widget vs halaman vs ekspor

- **Widget** = angka & grafik yang dibaca sekilas (dasbor, halaman analitik).
- **Halaman Analitik** = turunan `Filament\Pages\Dashboard` dengan filter periode + widget; tanpa ekspor.
- **Halaman Laporan** = turunan `Dashboard` dengan filter periode, **tabel formal** (widget tabel data kustom),
  tombol **Unduh Excel** dan **Cetak / PDF**. Angka di layar, Excel, dan cetak berasal dari **satu objek
  `TabelLaporan`** (§3.8) — tidak bisa berbeda.

---

## 2. Commands

### 2.1 Fase 5A
```bash
composer require openspout/openspout:^4.32 --no-interaction
php artisan make:model Wilayah --migration --factory --no-interaction
php artisan make:migration add_wilayah_id_to_pelanggan_table --table=pelanggan --no-interaction
php artisan make:policy WilayahPolicy --model=Wilayah --no-interaction
php artisan make:filament-resource Wilayah --panel=ksm --simple --no-interaction
php artisan make:class Support/Periode --no-interaction
php artisan make:class Support/Laporan/TabelLaporan --no-interaction
php artisan make:class Support/Laporan/KolomLaporan --no-interaction
php artisan make:class Support/Laporan/EksporXlsx --no-interaction
php artisan make:class Support/Laporan/LaporanOperasional --no-interaction
php artisan make:enum Enums/FormatAngka --string --no-interaction
php artisan make:test --phpunit PeriodeTest --no-interaction
php artisan make:test --phpunit WilayahTest --no-interaction
php artisan make:test --phpunit LaporanOperasionalTest --no-interaction
php artisan make:test --phpunit DasborMasterTest --no-interaction
```
`composer require` hanya mencantumkan paket yang **sudah terpasang** (versi 4.32.0 dari `filament/actions`) —
disetujui pemilik. Cek `php artisan make:enum --help` **[cek]** (opsi `--string`); bila tidak ada, tulis enum manual.

Ditulis **manual** (tanpa generator): semua widget (jebakan README §8: generator `--stats-overview/--chart/--table`
menghasilkan kelas & view salah), halaman turunan `Dashboard` (generator membuat Page + view yang tidak dipakai),
view `resources/views/filament/laporan/kop.blade.php`.

Setelah generator: `make:model Wilayah --migration` membuat tabel `wilayahs` → ganti ke **`wilayah`** +
`#[Table('wilayah')]`. Resource: pola file-tunggal proyek
`app/Filament/Ksm/Resources/Wilayahs/WilayahResource.php` + `Pages/ManageWilayahs.php`; hapus `Schemas/`/`Tables/`
bila dibuat.

### 2.2 Fase 5B
```bash
php artisan make:model KategoriTransaksi --migration --factory --no-interaction
php artisan make:model Mitra --migration --factory --no-interaction
php artisan make:model TransaksiKas --migration --factory --no-interaction
php artisan make:migration add_buku_kas_to_ksm_table --table=ksm --no-interaction
php artisan make:policy KategoriTransaksiPolicy --model=KategoriTransaksi --no-interaction
php artisan make:policy MitraPolicy --model=Mitra --no-interaction
php artisan make:policy TransaksiKasPolicy --model=TransaksiKas --no-interaction
php artisan make:filament-resource KategoriTransaksi --panel=ksm --simple --no-interaction
php artisan make:filament-resource Mitra --panel=ksm --simple --no-interaction
php artisan make:filament-resource TransaksiKas --panel=ksm --simple --no-interaction
php artisan make:enum Enums/JenisTransaksi --string --no-interaction
php artisan make:enum Enums/JenisMitra --string --no-interaction
php artisan make:class Support/RingkasanKas --no-interaction
php artisan make:class Support/Laporan/LaporanKeuangan --no-interaction
php artisan make:test --phpunit BukuKasTest --no-interaction
php artisan make:test --phpunit RingkasanKasTest --no-interaction
php artisan make:test --phpunit MitraTest --no-interaction
```
Nama tabel: `kategori_transaksi`, `mitra`, `transaksi_kas` + `#[Table]`. **Urutan migrasi:** `kategori_transaksi`
dan `mitra` sebelum `transaksi_kas` (FK). Folder resource: `KategoriTransaksis/`, `Mitras/`, `TransaksiKas/`
(rapikan nama yang dibuat generator agar persis seperti di §6).

### 2.3 Fase 5C
```bash
php artisan make:model JenisMaterial --migration --factory --no-interaction
php artisan make:model CatatanMaterial --migration --factory --no-interaction
php artisan make:policy JenisMaterialPolicy --model=JenisMaterial --no-interaction
php artisan make:policy CatatanMaterialPolicy --model=CatatanMaterial --no-interaction
php artisan make:filament-resource JenisMaterial --panel=ksm --simple --no-interaction
php artisan make:filament-resource CatatanMaterial --panel=ksm --simple --no-interaction
php artisan make:enum Enums/KelompokMaterial --string --no-interaction
php artisan make:enum Enums/AktivitasMaterial --string --no-interaction
php artisan make:class Support/RingkasanMaterial --no-interaction
php artisan make:class Support/RingkasanKinerja --no-interaction
php artisan make:class Support/Laporan/LaporanLingkungan --no-interaction
php artisan make:class Support/Laporan/LaporanKemitraan --no-interaction
php artisan make:class Support/Laporan/LaporanKinerja --no-interaction
php artisan make:test --phpunit CatatanMaterialTest --no-interaction
php artisan make:test --phpunit RingkasanMaterialTest --no-interaction
php artisan make:test --phpunit KinerjaTest --no-interaction
```
Tabel: `jenis_material`, `catatan_material`. `jenis_material` sebelum `catatan_material`.

---

## 3. Aturan kanonik bersama (satu sumber, dipakai widget, laporan, Excel, dan tes)

### 3.1 Periode — `App\Support\Periode` (5A)
`final readonly class Periode`, properti: `int $tahun`, `?int $bulan` (null = setahun), `CarbonImmutable $dari`,
`CarbonImmutable $sampai` (keduanya awal hari, kalender desa).

- `static dariFilter(?array $filters): self` — **tidak pernah melempar**; data filter halaman **tidak divalidasi
  Filament** (dok. "Filtering widget data": "this data is not validated") dan bisa dimanipulasi lewat URL
  `?filters[...]` (`HasFilters::$filters` adalah `#[Url]`, `vendor/filament/filament/src/Pages/Dashboard/Concerns/HasFilters.php:17`):
  - `tahun` = `filter_var($filters['tahun'] ?? null, FILTER_VALIDATE_INT)`; sah bila 2020 ≤ t ≤ `Hari::ini()->year + 1`, selain itu `Hari::ini()->year`.
  - `bulan`: nilai `'semua'` → `null`; `filter_var(..., FILTER_VALIDATE_INT)` 1–12 → int; selain itu (kosong/tidak sah/array) → `Hari::ini()->month`.
  - `dari` = tanggal 1 bulan itu / 1 Januari; `sampai` = akhir bulan / 31 Desember.
- `static bulanIni(): self` = `dariFilter(null)`.
- `label(): string` → `"November 2026"` (`translatedFormat('F Y')`) / `"Tahun 2026"`.
- `dariString()`, `sampaiString()` → `Y-m-d` (bandingkan kolom `date` sebagai string — aturan `Hari`).
- `jumlahHari(): int` = jumlah hari dari `dari` s.d. `min(sampai, Hari::ini())` inklusif; 0 bila `dari` > hari ini.
- `hari(): list<string>` — tanggal `Y-m-d` dari `dari` s.d. `min(sampai, hari ini)` (kosong untuk periode depan).
- `bulanBulan(): list<CarbonImmutable>` — awal tiap bulan dalam periode (1 atau 12).
- `sebelumnya(): self` — bulan sebelumnya / tahun sebelumnya (laporan kinerja).
- `kunci(): string` → `'2026-11'` / `'2026'` (nama berkas Excel).
- `static pilihanTahun(): array<int, string>` — `Hari::ini()->year + 1` turun s.d. 2020.
- `static pilihanBulan(): array<string, string>` — `['semua' => 'Setahun penuh', '1' => 'Januari', … '12' => 'Desember']`.
- `static duaBelasBulanSampai(CarbonImmutable $bulanAkhir): list<CarbonImmutable>` — awal 12 bulan berakhir di `$bulanAkhir` (grafik tren; bukan objek `Periode`).

**Konsistensi:** semua widget ber-filter dan semua laporan membentuk periode **hanya** lewat `Periode::dariFilter($this->pageFilters)`
/ `($this->filters)`; widget di Dasbor (tanpa filter) mendapat `pageFilters = null` → bulan berjalan desa.

### 3.2 Tanggal kas iuran (5B)
Ekspresi tunggal: `COALESCE(pembayaran_iuran.dibayar_pada, pembayaran_iuran.periode)`.
- `PembayaranIuran::scopeDiterimaAntara(Builder $q, string $dari, string $sampai): Builder` →
  `$q->whereRaw('date(coalesce(dibayar_pada, periode)) between ? and ?', [$dari, $sampai])` (`date()` ada di SQLite & MySQL/MariaDB).
- `PembayaranIuran::tanggalKas(): CarbonImmutable` → `($this->dibayar_pada ?? $this->periode)->toImmutable()->startOfDay()` (untuk pengelompokan per bulan di PHP).
- Dipakai **hanya** oleh buku kas (`RingkasanKas`), laporan keuangan, kolom "Iuran diterima" kinerja petugas (5A, lihat §5.6) dan kemandirian biaya.
  Rekap Iuran, `IuranStatsOverview`, `IuranPerBulanChart`, tingkat penagihan tetap **per `periode`** (bulan iuran) — perbedaan disengaja dan dijelaskan di catatan tabel ("arus kas memakai tanggal diterima").
- Iuran yang masih dipegang petugas (belum disetor) **tetap pemasukan** pada tanggal diterima (konsisten dengan "lunas langsung" Fase 4); kartu saldo menyebut porsinya.

### 3.3 Saldo kas (5B) — `App\Support\RingkasanKas`
- Masuk(dari, sampai) = iuran (§3.2) + `TransaksiKas` jenis masuk (+ penjualan material §3.5, mulai 5C).
- Keluar(dari, sampai) = `TransaksiKas` jenis keluar.
- `saldoAkhir(string $tanggal): ?int` = `null` bila `ksm.kas_mulai` terisi dan `$tanggal < kas_mulai`;
  selain itu `ksm.saldo_awal + Masuk(kas_mulai ?? '0001-01-01', $tanggal) − Keluar(sama)`.
- `saldoAwal(string $tanggal): ?int` = `ksm.saldo_awal` bila `$tanggal === kas_mulai`; `null` bila `$tanggal < kas_mulai`;
  selain itu `saldoAkhir(tanggal − 1 hari)`. (kas_mulai null → saldoAkhir(tanggal − 1 hari), yang = saldo_awal sebelum catatan pertama.)
- Surplus periode = Masuk − Keluar periode (boleh negatif = "Defisit"). Arus (masuk/keluar/surplus) **selalu** ditampilkan; hanya kolom saldo yang bisa "—".

### 3.4 Cakupan per RT (5A) — `RingkasanPengangkutan::perWilayah(Periode $periode): list<array>`
Rincian yang dihitung: `pengangkutan_rincian` dari `pengangkutan` KSM ini dengan `tanggal` di `[dari, sampai]`.
Baris (urut `rw`, lalu `rt`):
1. **Setiap `Wilayah` KSM** (juga yang belum punya pelanggan), lalu
2. **"Tanpa RT"** — pelanggan aktif tanpa `wilayah_id` (tampil bila ada pelanggan aktif atau rincian),
3. **"Pelanggan nonaktif"** — semua pelanggan `aktif = false` (tampil bila ada rincian di periode).

Kolom per baris (pelanggan **aktif saat ini** untuk baris 1–2; status aktif tidak punya riwayat):
| Kunci | Rumus |
|---|---|
| `rumah_aktif` | jumlah pelanggan aktif di wilayah (baris 3: `null`) |
| `terlayani` | pelanggan aktif dengan ≥1 rincian `diangkut` di periode |
| `cakupan` | `terlayani / rumah_aktif`; `null` bila `rumah_aktif = 0` |
| `kunjungan` | jumlah rincian `diangkut` milik pelanggan baris itu |
| `rata_mingguan` | `kunjungan / rumah_aktif / (Periode::jumlahHari() / 7)`; `null` bila salah satu penyebut 0 |
| `tidak_bisa_diakses` | jumlah rincian `tidak_bisa_diakses` |
| `kg` | Σ `perkiraan_kg` |
| `jumlah_kk`, `penetrasi` | `wilayah.jumlah_kk`; `rumah_aktif / jumlah_kk` (null bila KK kosong/0) |

**Invarian (diuji):** Σ `kg` semua baris = `RingkasanPengangkutan::berat(dari, sampai)`; Σ `kunjungan` = jumlah rincian diangkut KSM di periode.
Turunan KSM: **RT terlayani** = jumlah baris jenis 1 dengan `terlayani ≥ 1` ("X dari Y RT"); **cakupan layanan KSM** =
Σ terlayani / Σ rumah_aktif baris 1–2.

### 3.5 Material (5C) — `App\Support\RingkasanMaterial`
- **Terpulihkan** = Σ `berat_kg` aktivitas `pilah` + Σ aktivitas `olah` di periode.
- **Terjual** = Σ `berat_kg` dan Σ `nilai_jual` aktivitas `jual` di periode.
- **Komposisi** = terpulihkan per `KelompokMaterial` (pilah + olah).
- **Stok perkiraan** per jenis s.d. `sampai` = Σ pilah − Σ jual (seluruh catatan s.d. tanggal itu; negatif ditampilkan apa adanya dengan warna `danger` = data pilah belum lengkap).
- Penjualan masuk buku kas pada `tanggal` catatan, label sumber **"Penjualan material"**.
- Normalisasi di model (`saving`): bukan `jual` → `mitra_id = null`, `nilai_jual = null`; bukan `olah` → `hasil_olah = null` (pola `PengangkutanRincian::butuhBerat`).

### 3.6 Indikator progres ekonomi sirkular (5C) — `App\Support\RingkasanKinerja::indikator(Periode): array<string, ?float>`
Nilai pecahan (0,73 = 73 %), `null` bila penyebut 0 → tampil "—" dengan keterangan "belum ada data …".
| Kunci | Rumus |
|---|---|
| `penagihan` | Σ iuran terkumpul **per `periode`** untuk bulan-bulan periode yang ≤ bulan berjalan **dan** ≥ bulan iuran pertama yang pernah tercatat di KSM (`RingkasanIuran::bulanPertama()`, baru — `min('periode')`; mencegah Januari–Maret 2026 sebelum aplikasi dipakai dihitung 0 %) ÷ (`RingkasanIuran::target()` × jumlah bulan itu). Target = Σ iuran bulanan pelanggan aktif **saat ini** (sama dengan dasbor Fase 3). |
| `cakupan` | cakupan layanan KSM (§3.4). |
| `pemulihan` | terpulihkan kg (§3.5) ÷ `RingkasanPengangkutan::berat(dari, sampai)`. Boleh > 100 %. |
| `kemandirian` | (iuran **kas** §3.2 + penjualan material) ÷ Keluar periode. **Tanpa** dukungan mitra & pemasukan lain. |

### 3.7 Data master: pilihan & penghapusan
- **Pilihan** = himpunan sah KSM ini, eksplisit `whereBelongsTo(Filament::getTenant())` (jebakan README §8: jangan
  mengandalkan scope implisit), **ditambah nilai tersimpan** walau sudah nonaktif (pola `PilihanPengguna`), sehingga rule `in`
  bawaan Select menolak id lain/KSM lain di server dan simpan-tanpa-perubahan tidak gagal.
  Kelas baru `App\Filament\Shared\PilihanMaster` (5A: `wilayah()`; 5B: `kategori()`, `mitra()`; 5C: `jenisMaterial()`, `pengepul()`), tiap method `(?int $tersimpan = null): array<int, string>`.
- **Hapus**: kebijakan `delete` = `isAdminKsm() && ! $model->dipakai()`. `dipakai()` di model:
  Wilayah → `pelanggan()->exists()`; KategoriTransaksi → `transaksiKas()->exists()`; Mitra → `transaksiKas()->exists() || catatanMaterial()->exists()` (5C);
  JenisMaterial → `catatanMaterial()->exists()`. `deleteAny` = false (tidak ada hapus massal).

### 3.8 Satu sumber tabel laporan — `TabelLaporan` (5A)
- `App\Enums\FormatAngka: string` — `Teks`, `Angka`, `Rupiah`, `Kg`, `Persen`, `Tanggal`; method:
  - `tampil(mixed $nilai): string` — `null` → `'—'`; Rupiah → `RingkasanIuran::rupiah()`; Kg → `RingkasanPengangkutan::kg()`;
    Persen → `Number::percentage($nilai * 100, precision: 0, locale: 'id')`; Angka → `Number::format($nilai, maxPrecision: 1, locale: 'id')`;
    Tanggal → `Carbon::parse($nilai)->translatedFormat('d M Y')`.
  - `rataKanan(): bool` (semua kecuali Teks/Tanggal).
  - `formatExcel(): ?string` — Rupiah `'#,##0'`, Kg `'#,##0.0'`, Persen `'0%'`, Angka `'#,##0.##'`, lainnya null.
- `App\Support\Laporan\KolomLaporan` — `final readonly`: `string $kunci`, `string $label`, `FormatAngka $format`,
  `bool $formatDariBaris = false` (true → format sel diambil dari kunci `__format` baris itu, berisi nilai `FormatAngka`;
  dipakai hanya oleh tabel "Ringkasan kinerja" yang mencampur Rupiah/Kg/Angka dalam satu kolom).
  `formatUntuk(array $baris): FormatAngka` = `$formatDariBaris ? FormatAngka::from($baris['__format']) : $format` — dipakai layar **dan** Excel.
- `App\Support\Laporan\TabelLaporan` — `final readonly`: `string $judul`, `list<KolomLaporan> $kolom`,
  `list<array<string, scalar|null>> $baris`, `?array $total` (baris total, atau null), `?string $catatan`.
  Method `rekamanTabel(): array<string, array>` → baris dengan kunci `'0'…'n'` + `'total' => [...$total, '__total' => true]`.
- Pembangun per domain (`App\Support\Laporan\LaporanOperasional` 5A, `LaporanKeuangan` 5B,
  `LaporanLingkungan`/`LaporanKemitraan`/`LaporanKinerja` 5C): `__construct(Ksm $ksm)`, method publik per bagian
  `(Periode $periode): TabelLaporan`, dan `semua(Periode $periode): list<TabelLaporan>` (urutan bagian halaman).
  **Widget tabel layar, Excel, dan cetak memanggil method yang sama.**

### 3.9 KSM aktif mencatat (master, 5A)
`Ksm::RELASI_CATATAN = ['pengangkutan', 'pembayaranIuran', 'setoran']` (5B tambah `'transaksiKas'`, 5C `'catatanMaterial'`).
`Ksm::scopeAktifMencatat(Builder $q, CarbonInterface $sejak)` = `where(fn ($q) => foreach relasi: orWhereHas($relasi, fn ($r) => $r->where('created_at', '>=', $sejak)))`.
Batas `$sejak = now()->subDays(7)` (instan UTC — `created_at` adalah timestamp, bukan tanggal desa). Dipakai stat, badge, dan filter master — satu aturan.

---

## 4. Infrastruktur halaman (5A)

### 4.0 Namespace komponen (nama singkat di rencana ini = impor berikut)
| Nama singkat | Namespace | Docs |
|---|---|---|
| TextInput, Select, DatePicker, Textarea, Toggle, ToggleButtons, FileUpload | `Filament\Forms\Components\*` | https://filamentphp.com/docs/5.x/forms/overview |
| TextEntry | `Filament\Infolists\Components\TextEntry` | https://filamentphp.com/docs/5.x/infolists/text-entry |
| Section, View | `Filament\Schemas\Components\*` | https://filamentphp.com/docs/5.x/schemas/overview |
| Get, Set | `Filament\Schemas\Components\Utilities\*` | https://filamentphp.com/docs/5.x/forms/overview#field-utility-injection |
| TextColumn, IconColumn | `Filament\Tables\Columns\*` | https://filamentphp.com/docs/5.x/tables/columns/overview |
| Summarizer, Sum | `Filament\Tables\Columns\Summarizers\*` | https://filamentphp.com/docs/5.x/tables/summaries |
| SelectFilter, Filter, TernaryFilter | `Filament\Tables\Filters\*` | https://filamentphp.com/docs/5.x/tables/filters/overview |
| Action, ActionGroup, CreateAction, EditAction, DeleteAction | `Filament\Actions\*` | https://filamentphp.com/docs/5.x/actions/overview |
| StatsOverviewWidget, Stat, ChartWidget, TableWidget, AccountWidget | `Filament\Widgets\*`, `Filament\Widgets\StatsOverviewWidget\Stat` | https://filamentphp.com/docs/5.x/widgets/overview |
| InteractsWithPageFilters | `Filament\Widgets\Concerns\InteractsWithPageFilters` | https://filamentphp.com/docs/5.x/widgets/overview#filtering-widget-data |
| HasFiltersForm | `Filament\Pages\Dashboard\Concerns\HasFiltersForm` | sama |
| Heroicon, FontWeight | `Filament\Support\Icons\Heroicon`, `Filament\Support\Enums\FontWeight` | — |
| Builder (query form/filter) | `Illuminate\Database\Eloquent\Builder` (Summarizer `using`: `Illuminate\Database\Query\Builder`) | — |
| Closure | `Closure` (rule kustom `function (string $attribute, mixed $value, Closure $fail)`) | https://laravel.com/docs/13.x/validation#using-closures |

### 4.1 Dasbor desa — `App\Filament\Ksm\Pages\Dashboard` (BARU, menggantikan `Filament\Pages\Dashboard`)
- Location: `app/Filament/Ksm/Pages/Dashboard.php`, `extends Filament\Pages\Dashboard`. Docs:
  https://filamentphp.com/docs/5.x/widgets/overview#customizing-the-dashboard-page
- Slug otomatis `dashboard`, `$routePath` `'/'` (diwarisi) → nama rute `filament.ksm.pages.dashboard` **tidak berubah**.
- `KsmPanelProvider`: hapus `Filament\Pages\Dashboard::class` dari `->pages([...])` (halaman baru ditemukan oleh `discoverPages`);
  `->navigationGroups(['Analitik', 'Data Input', 'Laporan', 'Data Master'])`.
- `getWidgets(): array` — **daftar eksplisit** (default `Filament::getWidgets()` akan menampilkan semua widget analitik di dasbor;
  `vendor/filament/filament/src/Pages/Dashboard.php:52`). Urutan daftar = urutan tampil (`canView()` menyaring per peran):
  ```
  5A: AccountWidget, IuranStatsOverview, IuranPerBulanChart, OperasionalStatsOverview, BeratPerHariChart, KelengkapanDataStats,
      TugasPetugasStatsOverview, DaftarTugasHariIni, WargaStatsOverview, RiwayatIuranSaya, RiwayatPengangkutanSaya
  5B: sisipkan KeuanganStats (pertama setelah AccountWidget), TrenKasChart dan KomposisiPemasukanChart (setelah IuranPerBulanChart)
  5C: sisipkan ProgresEkonomiSirkularStats (setelah KeuanganStats), LingkunganStats dan KemitraanStats (setelah OperasionalStatsOverview)
  ```
- Tanpa filter (dasbor dipakai juga oleh petugas & warga). Dasbor utama tetap halaman pertama untuk semua peran:
  `$navigationSort = -2` (bawaan) lebih kecil dari semua halaman analitik/laporan (dok. "Creating multiple dashboards":
  dasbor utama = yang pertama bisa diakses menurut urutan navigasi).

### 4.2 Filter periode bersama — trait `App\Filament\Ksm\Pages\Concerns\PunyaFilterPeriode`
- `use Filament\Pages\Dashboard\Concerns\HasFiltersForm;` di dalam trait.
- `filtersForm(Schema $schema): Schema` →
  `Filament\Schemas\Components\Section::make()->columns(2)->extraAttributes(['class' => 'ksm-filter-periode'])->schema([...])` — Docs: https://filamentphp.com/docs/5.x/schemas/sections
  - Field `tahun` — Component: `Filament\Forms\Components\Select` — Docs: https://filamentphp.com/docs/5.x/forms/select
    Validation: tidak ada (filter live tidak divalidasi; disanitasi §3.1). Config: `->label('Tahun')->options(fn (): array => Periode::pilihanTahun())->default((string) Hari::ini()->year)->selectablePlaceholder(false)`.
  - Field `bulan` — Component: `Filament\Forms\Components\Select` — Docs: https://filamentphp.com/docs/5.x/forms/select
    Validation: tidak ada (§3.1). Config: `->label('Bulan')->options(Periode::pilihanBulan())->default((string) Hari::ini()->month)->selectablePlaceholder(false)`.
- `periode(): Periode` → `Periode::dariFilter($this->filters)`.
- `getSubheading(): string` → `'Periode: '.$this->periode()->label()`.
- Filter disimpan per tenant di sesi (bawaan `HasFilters`, dok. "Persisting widget filters").

### 4.3 Basis halaman analitik — `App\Filament\Ksm\Pages\Analitik\HalamanAnalitik` (abstract)
`extends Filament\Pages\Dashboard`, `use PunyaFilterPeriode;`
- `protected static string|UnitEnum|null $navigationGroup = 'Analitik';`
- `public static function canAccess(): bool` → `(bool) auth()->user()?->isAdminKsm()` (Filament memeriksa saat mount → 403;
  Docs: https://filamentphp.com/docs/5.x/navigation/custom-pages#authorization).
- `getColumns()` → `2`.
- Turunan menetapkan `protected static string $routePath` dan `protected static ?string $slug` — **keduanya** `'analitik/operasional'`
  dst. (tanpa garis miring awal, seperti contoh dok. `'finance'`; `Dashboard::getRoutePath()` memakai `$routePath`, nama rute dari
  `$slug` → `filament.ksm.pages.analitik.operasional`, `vendor/filament/filament/src/Pages/Concerns/HasRoutes.php:55-90`),
  `$title`, `$navigationLabel`, `$navigationIcon`, `$navigationSort`, `getWidgets()`. Kelas abstrak dilewati oleh discovery
  (`vendor/filament/filament/src/Panel/Concerns/HasComponents.php` — `isAbstract()`).

### 4.4 Basis halaman laporan — `App\Filament\Ksm\Pages\Laporan\HalamanLaporan` (abstract)
`extends Filament\Pages\Dashboard`, `use PunyaFilterPeriode;`
- Grup `'Laporan'`; `canAccess()` admin; `getColumns()` → `1`.
- `abstract protected function pembangun(): object` (instans `Laporan…` §3.8 untuk tenant) dan
  `abstract protected static function jenisLaporan(): string` (`'operasional'`, `'keuangan'`, …).
- `content(Schema $schema): Schema` →
  ```php
  $schema->components([
      \Filament\Schemas\Components\View::make('filament.laporan.kop')
          ->viewData(fn (): array => ['ksm' => Filament::getTenant(), 'judul' => $this->getTitle(), 'periode' => $this->periode()->label(), 'pencetak' => auth()->user()->name]),
      $this->getFiltersFormContentComponent(),
      $this->getWidgetsContentComponent(),
      \Filament\Schemas\Components\View::make('filament.laporan.ttd'),
  ]);
  ```
  (`Dashboard::content()` hanya menyusun filtersForm + widget grid, `vendor/filament/filament/src/Pages/Dashboard.php:87-94`;
  kop ditambahkan di depan.) Docs: https://filamentphp.com/docs/5.x/schemas/custom-components#view-components
- `getHeaderActions()`:
  - **Action `unduhExcel`** — Component: `Filament\Actions\Action` — Docs: https://filamentphp.com/docs/5.x/actions/overview
    Location: header halaman. Visibility: selalu (halaman sudah admin-only). Authorization: `->authorize(fn (): bool => (bool) auth()->user()?->isAdminKsm())`.
    Config: `->label('Unduh Excel')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')`.
    Behavior: (1) `$periode = $this->periode()`; (2) `$tabel = $this->pembangun()->semua($periode)`;
    (3) `return EksporXlsx::unduh(static::jenisLaporan(), $this->getTitle(), Filament::getTenant(), $periode, $tabel);`
    (Livewire mengubah `StreamedResponse` menjadi unduhan, `vendor/livewire/livewire/src/Features/SupportFileDownloads/SupportFileDownloads.php:12-38`;
    Docs: https://livewire.laravel.com/docs/downloads).
  - **Action `cetak`** — Component: `Filament\Actions\Action` — Location: header. Visibility: selalu. Authorization: sama.
    Config: `->label('Cetak / PDF')->icon(Heroicon::OutlinedPrinter)->color('gray')->alpineClickHandler('window.print()')` (`vendor/filament/actions/src/Action.php:322`).
    Behavior: tanpa request server; browser membuka dialog cetak (pilih "Simpan sebagai PDF").

### 4.5 Widget tabel laporan — `App\Filament\Ksm\Widgets\Laporan\TabelLaporanWidget` (abstract)
`extends Filament\Widgets\TableWidget`, `use Filament\Widgets\Concerns\InteractsWithPageFilters;`
Docs: https://filamentphp.com/docs/5.x/widgets/overview#table-widgets · https://filamentphp.com/docs/5.x/tables/custom-data
- `protected int|string|array $columnSpan = 'full';` · `canView()` → `isAdminKsm()`.
- `abstract protected static function kolom(): list<KolomLaporan>` (statis: kolom tidak bergantung periode) dan
  `abstract protected function tabel(Periode $periode): TabelLaporan` (memanggil pembangun §3.8 dengan `Filament::getTenant()`).
- `private function tabelTerkini(): TabelLaporan` — dimemo dalam properti **non-publik** `private array $cacheTabel = []` dengan kunci
  `Periode::dariFilter($this->pageFilters)->kunci()` (properti non-publik tidak ikut snapshot Livewire, jadi cache hanya hidup satu request;
  kunci periode mencegah cache basi bila prop reaktif berubah di request yang sama).
- `table(Table $table): Table`:
  - `->records(fn (): array => $this->tabelTerkini()->rekamanTabel())` — **dievaluasi saat render**, sesudah prop reaktif `pageFilters`
    diperbarui (`vendor/filament/tables/src/Concerns/HasRecords.php:115-140`). Jangan menghitung data di `table()` langsung.
  - `->heading(fn (): string => $this->tabelTerkini()->judul)` dan `->description(fn (): ?string => $this->tabelTerkini()->catatan)`.
  - kolom: tiap `KolomLaporan` dari `static::kolom()` → `Filament\Tables\Columns\TextColumn::make($k->kunci)` — Docs: https://filamentphp.com/docs/5.x/tables/columns/text —
    Config: `->label($k->label)->formatStateUsing(fn ($state, array $record): string => $k->formatUntuk($record)->tampil($state))->placeholder('—')->alignEnd($k->format->rataKanan())->weight(fn (array $record): ?FontWeight => ($record['__total'] ?? false) ? FontWeight::Bold : null)` (`Filament\Support\Enums\FontWeight`).
    `kolom()` statis harus sama dengan `TabelLaporan::$kolom` pembangun — pembangun mengambil definisi kolomnya dari konstanta/metode statis yang sama (satu definisi per bagian).
  - `->paginated(false)` (cetak memuat semua baris), tanpa sort/search, `->emptyStateHeading('Belum ada data pada periode ini')`.
- **Keamanan data kustom** (dok. "Securing custom data"): Filament tidak menerapkan scope global/kebijakan untuk rekaman array —
  pembangun **wajib** memfilter `whereBelongsTo($ksm)` di setiap query.

### 4.6 Excel — `App\Support\Laporan\EksporXlsx` (5A)
`static unduh(string $jenis, string $judul, Ksm $ksm, Periode $periode, array $tabel): StreamedResponse`
- Nama berkas: `"laporan-{$jenis}-{$ksm->slug}-{$periode->kunci()}.xlsx"`.
- `response()->streamDownload(function () use (...) { $writer = new \OpenSpout\Writer\XLSX\Writer(); $writer->openToFile('php://output'); … $writer->close(); }, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])`.
- Satu **sheet per `TabelLaporan`** (sheet pertama memakai `getCurrentSheet()`, berikutnya `addNewSheetAndMakeItCurrent()`;
  `vendor/openspout/openspout/src/Writer/AbstractWriterMultiSheets.php:47,62`); nama sheet = judul, buang `[]:*?/\`, maks 31 karakter, unik.
- Isi sheet: baris 1 nama KSM (tebal), baris 2 judul laporan, baris 3 `"Periode: {label}"`, baris 4 `"Dibuat: {now WITA d M Y H:i}"`,
  baris kosong, baris judul kolom (tebal), baris data, baris total (tebal), lalu `catatan` bila ada.
- Sel angka ditulis **sebagai angka** (bukan teks berformat): Rupiah int, Kg/Angka float, Persen pecahan; gaya
  `(new \OpenSpout\Common\Entity\Style\Style())->setFormat($k->formatUntuk($baris)->formatExcel())` (`Style.php:443`), tebal `->setFontBold()` (`Style.php:157`);
  kunci internal `__total`/`__format` tidak ditulis sebagai kolom;
  `Row::fromValuesWithStyles()` (`Row.php:55`). `null` → sel kosong.
- Docs: https://github.com/openspout/openspout/blob/4.x/docs/documentation.md

### 4.7 Cetak — `resources/views/filament/laporan/kop.blade.php` (5A)
Gaya inline (panel memakai CSS prebuilt Filament; kelas Tailwind baru tidak tersedia — README §6). Isi:
- `<style>` dengan:
  ```css
  .ksm-kop-cetak, .ksm-ttd-cetak { display: none; }
  @media print {
    .fi-sidebar, .fi-sidebar-close-overlay, .fi-topbar-ctn, .fi-header-actions-ctn, .ksm-filter-periode, .fi-ta-header-toolbar { display: none !important; }
    .fi-main-ctn, .fi-main { margin: 0 !important; padding: 0 !important; max-width: none !important; }
    .ksm-kop-cetak, .ksm-ttd-cetak { display: block !important; }
    .fi-wi-widget, .fi-ta-ctn { break-inside: avoid; }
    @page { size: A4; margin: 12mm; }
  }
  ```
  (kelas diverifikasi di `vendor/filament/filament/resources/views`: `fi-sidebar`, `fi-topbar-ctn`, `fi-header-actions-ctn`, `fi-main-ctn`; `fi-ta-header-toolbar` di tables. **[cek]** `fi-main`, `fi-wi-widget`, `fi-ta-ctn` — buang selektor yang tidak ada.)
- `<div class="ksm-kop-cetak">`: logo (`$ksm->logoUrl()`, tinggi 56px, bila ada), nama KSM (tebal), "Desa {desa}", garis,
  judul laporan, periode, "Dicetak {Hari::ini() d M Y} oleh {pencetak}". Semua nilai di-escape `{{ }}`.
- `<div class="ksm-ttd-cetak">` di **akhir** halaman — tambahkan sebagai komponen `View::make('filament.laporan.ttd')` setelah widget grid
  di `HalamanLaporan::content()`: dua kolom "Ketua KSM" / "Bendahara" dengan garis tanda tangan kosong.
- Verifikasi visual: di Browser pane, sisipkan aturan di atas tanpa `@media print` lewat `javascript_tool`, lalu screenshot.

---

## 5. Fase 5A — Fondasi & data sekarang

### 5.1 Model `Wilayah` (BARU)
```
Model: Wilayah   (#[Table('wilayah')], #[Fillable(['rt', 'rw', 'dusun', 'jumlah_kk'])])
  Attributes:
    - id: bigint, primary
    - ksm_id: bigint, foreign(ksm.id), cascadeOnDelete, required
    - rt: unsignedSmallInteger, required            // 1–999
    - rw: unsignedSmallInteger, nullable            // banyak desa WITA tanpa RW
    - dusun: string(100), nullable                  // dusun/lingkungan
    - jumlah_kk: unsignedInteger, nullable          // untuk penetrasi layanan
    - created_at, updated_at: timestamp
  Index: unique(ksm_id, rw, rt)   // DB tidak menjaga rw NULL (NULL ≠ NULL) — validasi form menjaga (§5.2)
  Casts: rt integer, rw integer, jumlah_kk integer
  Relationships:
    - belongsTo: Ksm via ksm_id
    - hasMany: Pelanggan via wilayah_id
  Methods:
    - label(): string → sprintf('RT %02d', rt) . (rw ? sprintf(' / RW %02d', rw) : '') . (dusun ? " · {$dusun}" : '')
    - dipakai(): bool → pelanggan()->exists()
    - scopeUrut(Builder): orderBy('rw')->orderBy('rt')
Ksm: + wilayah(): HasMany (relasi tenant)
```

**Migrasi `add_wilayah_id_to_pelanggan_table` (aditif — revisi saat implementasi, syarat sesi utama):**
- `up()`: `foreignId('wilayah_id')->nullable()->after('tarif_layanan_id')->constrained('wilayah')->nullOnDelete()`, `index(['ksm_id', 'wilayah_id'])`.
  Kolom teks lama `rt`/`rw` **tidak disentuh** (tidak dipakai aplikasi lagi, tidak di `#[Fillable]`) supaya tidak ada nilai yang hilang;
  hapus di migrasi terpisah setelah produksi dipastikan kosong.
- `down()`: drop index + FK/kolom `wilayah_id`.

**`Pelanggan` (ubah):** Fillable: ganti `'rt', 'rw'` dengan `'wilayah_id'`; relasi `wilayah(): BelongsTo`.
`WilayahFactory`: `rt` = `fake()->unique()->numberBetween(1, 50)`, `rw` = `fake()->numberBetween(1, 10)`, `jumlah_kk` null.

### 5.2 Resource `WilayahResource` (BARU)
- Command: `php artisan make:filament-resource Wilayah --panel=ksm --simple --no-interaction`
- Location: `app/Filament/Ksm/Resources/Wilayahs/WilayahResource.php`, page `Pages/ManageWilayahs.php` (pola `TarifLayananResource`).
  Docs: https://filamentphp.com/docs/5.x/resources/overview
- `$tenantRelationshipName = 'wilayah'`, grup `'Data Master'`, `$navigationSort = 15`, icon `Heroicon::OutlinedMap`,
  `$modelLabel`/`$pluralModelLabel = 'wilayah (RT/RW)'`, `$navigationLabel = 'Wilayah (RT/RW)'`.
- `getEloquentQuery()`: admin → `parent::getEloquentQuery()->withCount('pelanggan')`; lainnya → `whereRaw('1 = 0')`.
- **Form** (`$schema->columns(2)`):
  - Field `rt` — Component: `Filament\Forms\Components\TextInput` — Docs: https://filamentphp.com/docs/5.x/forms/text-input
    Validation: required, integer, min:1, max:999, unik per (KSM, rw, rt).
    Imports: `Filament\Schemas\Components\Utilities\Get`.
    Config: `->label('RT')->numeric()->integer()->minValue(1)->maxValue(999)->required()->scopedUnique(column: 'rt', ignoreRecord: true, modifyQueryUsing: fn (Builder $query, Get $get): Builder => filled($get('rw')) ? $query->where('rw', (int) $get('rw')) : $query->whereNull('rw'))->validationMessages(['unique' => 'RT ini sudah ada di daftar.'])`
    (`scopedUnique` mengevaluasi `modifyQueryUsing` lewat `$component->evaluate()` yang menyuntikkan `Get`, `vendor/filament/forms/src/Components/Concerns/CanBeValidated.php:599-625`.)
  - Field `rw` — `Filament\Forms\Components\TextInput` — Docs: sama — Validation: nullable, integer, min:1, max:999 —
    Config: `->label('RW')->numeric()->integer()->minValue(1)->maxValue(999)->live(onBlur: true)` (rt divalidasi ulang dengan rw terbaru saat simpan).
  - Field `dusun` — `TextInput` — Validation: nullable, max:100 — Config: `->label('Dusun / lingkungan')->maxLength(100)`.
  - Field `jumlah_kk` — `TextInput` — Validation: nullable, integer, min:0, max:100000 —
    Config: `->label('Jumlah KK')->numeric()->integer()->minValue(0)->maxValue(100_000)->helperText('Opsional — untuk menghitung persentase KK yang berlangganan.')`.
- **Table** — `->defaultSort(fn (Builder $q) => $q->orderBy('rw')->orderBy('rt'))` **[cek]** (bila `defaultSort` tidak menerima closure, pakai `->modifyQueryUsing(fn ($q) => $q->urut())`):
  - Column `label` — `Filament\Tables\Columns\TextColumn` — Docs: https://filamentphp.com/docs/5.x/tables/columns/text — Config: `->label('Wilayah')->state(fn (Wilayah $record): string => $record->label())`.
  - Column `jumlah_kk` — `TextColumn` — Config: `->label('KK')->numeric(locale: 'id')->placeholder('—')`.
  - Column `pelanggan_count` — `TextColumn` — Config: `->label('Pelanggan')->numeric()`.
- **Actions**: `Filament\Actions\ActionGroup::make([EditAction::make(), DeleteAction::make()->modalDescription('RT tanpa pelanggan ini akan dihapus.')])`
  (Docs: https://filamentphp.com/docs/5.x/actions/delete). Visibility/Authorization: `WilayahPolicy` (§5.9) — `DeleteAction` tersembunyi & ditolak bila `dipakai()`.
  Header: `CreateAction::make()` (Docs: https://filamentphp.com/docs/5.x/actions/create) — `ksm_id` dari relasi tenant.

### 5.3 `PelangganResource` (UBAH)
- **Form** bagian "Lokasi": hapus `TextInput rt`/`rw`; tambah
  Field `wilayah_id` — Component: `Filament\Forms\Components\Select` — Docs: https://filamentphp.com/docs/5.x/forms/select —
  Validation: nullable, `in` otomatis dari opsi (§3.7) —
  Config: `->label('RT / RW')->options(fn (?Pelanggan $record): array => PilihanMaster::wilayah($record?->wilayah_id))->searchable()->preload()->placeholder('Belum ditetapkan')->helperText(fn (): ?string => PilihanMaster::wilayah() === [] ? 'Isi daftar RT/RW di Data Master → Wilayah (RT/RW).' : null)`.
- **Infolist**: ganti `TextEntry rt`/`rw` dengan `Filament\Infolists\Components\TextEntry::make('wilayah')` — Docs: https://filamentphp.com/docs/5.x/infolists/text-entry — Config: `->label('RT / RW')->state(fn (Pelanggan $record): ?string => $record->wilayah?->label())->placeholder('—')`.
- **Table**: ganti `TextColumn rt` dengan `TextColumn::make('wilayah_label')` — Config: `->label('RT / RW')->state(fn (Pelanggan $record): ?string => $record->wilayah?->label())->placeholder('—')->toggleable()`; tambah `with('wilayah')` di query.
- **Filters** (admin & petugas):
  - Filter `wilayah_id` — Component: `Filament\Tables\Filters\SelectFilter` — Docs: https://filamentphp.com/docs/5.x/tables/filters/select — Config: `->label('RT / RW')->options(fn (): array => PilihanMaster::wilayah())` (opsi eksplisit, bukan `->relationship()` — jebakan README §8).
  - Filter `tanpa_rt` — Component: `Filament\Tables\Filters\Filter` — Docs: https://filamentphp.com/docs/5.x/tables/filters/custom — Config: `->label('Tanpa RT')->toggle()->query(fn (Builder $q): Builder => $q->whereNull('wilayah_id'))`.
- `PilihanMaster::wilayah(?int $tersimpan = null)`: `Wilayah::query()->whereBelongsTo(Filament::getTenant())->urut()->get()->mapWithKeys(fn ($w) => [$w->id => $w->label()])` (Wilayah tidak punya status nonaktif → nilai tersimpan selalu ikut; parameter disediakan demi keseragaman).
- `DaftarTugasHariIni` (petugas): deskripsi kolom `alamat` → `$record->wilayah?->label()`; `with(['ksm', 'akunWarga', 'wilayah'])`.

### 5.4 `RingkasanPengangkutan` (UBAH)
Tambah method (semua dibatasi `whereBelongsTo($this->ksm)` dan, bila ada, `$this->petugas`):
- `perWilayah(Periode $periode): list<array>` — §3.4. Implementasi: (1) satu query rincian periode join `pelanggan` →
  `get(['pengangkutan_rincian.pelanggan_id', 'status', 'perkiraan_kg', 'pelanggan.wilayah_id', 'pelanggan.aktif'])`;
  (2) pelanggan aktif per wilayah `groupBy('wilayah_id')->count()`; (3) kelompokkan di PHP. Kunci baris: `wilayah` (label), `jumlah_kk`, `rumah_aktif`, `terlayani`, `cakupan`, `penetrasi`, `kunjungan`, `rata_mingguan`, `tidak_bisa_diakses`, `kg`.
- `cakupanKsm(Periode $periode): ?float` dan `rtTerlayani(Periode $periode): array{0: int, 1: int}` — dari `perWilayah()` (bukan query terpisah).
- `hariPengangkutan(Periode $periode): int` — jumlah tanggal berbeda yang punya ≥1 rincian `diangkut`.
- `perPetugas(Periode $periode): list<array>` — petugas KSM yang aktif **atau** punya ronde di periode: `petugas`, `rumah_tugas` (pelanggan aktif dengan `petugas_id` itu), `hari_bertugas` (tanggal ronde berbeda), `rumah_dicatat` (rincian), `diangkut`, `kg`, `iuran_diterima` (`PembayaranIuran` `petugas_id` itu, `diterimaAntara` §3.2 — scope ini ikut dibuat di 5A), `belum_disetor` (saat ini, `belumDisetor()`).
- `perHari(Periode $periode): list<array>` — per tanggal yang punya ronde: `tanggal`, `petugas` (jumlah ronde), `dicatat`, `diangkut`, `tidak_ada_sampah`, `tidak_bisa_diakses`, `kg`.
- `beratPerBulan(Periode $periode): array<string, float>` — `'Y-m'` → kg (untuk grafik periode setahun).

### 5.5 `LaporanOperasional` (BARU) — `App\Support\Laporan\LaporanOperasional`
| Method | Judul | Kolom (kunci · label · format) | Total |
|---|---|---|---|
| `perWilayah(Periode)` | Realisasi pengangkutan per RT | wilayah · Wilayah · Teks; jumlah_kk · KK · Angka; rumah_aktif · Rumah aktif · Angka; terlayani · Terlayani · Angka; cakupan · Cakupan · Persen; penetrasi · Pelanggan/KK · Persen; kunjungan · Kunjungan diangkut · Angka; rata_mingguan · Rata-rata/rumah/minggu · Angka; tidak_bisa_diakses · Tidak bisa diakses · Angka; kg · Perkiraan · Kg | Σ kolom angka; cakupan & rata-rata dihitung ulang dari Σ (bukan Σ persen); penetrasi & KK "—" |
| `perPetugas(Periode)` | Kinerja petugas | petugas · Petugas · Teks; rumah_tugas · Rumah tugas · Angka; hari_bertugas · Hari bertugas · Angka; rumah_dicatat · Rumah dicatat · Angka; diangkut · Diangkut · Angka; kg · Perkiraan · Kg; iuran_diterima · Iuran diterima · Rupiah; belum_disetor · Belum disetor (saat ini) · Rupiah | Σ |
| `perHari(Periode)` | Rekap harian | tanggal · Tanggal · Tanggal; petugas · Ronde · Angka; dicatat · Rumah dicatat · Angka; diangkut · Diangkut · Angka; tidak_ada_sampah · Tidak ada sampah · Angka; tidak_bisa_diakses · Tidak bisa diakses · Angka; kg · Perkiraan · Kg | Σ |
`catatan` bagian pertama: "Rumah aktif = status pelanggan saat laporan dibuat. Iuran diterima memakai tanggal diterima."

### 5.6 Widget panel desa (5A)
Semua: `canView()` → `(bool) auth()->user()?->isAdminKsm()`; ditulis manual; namespace `App\Filament\Ksm\Widgets\…`.
Widget yang menerima periode `use Filament\Widgets\Concerns\InteractsWithPageFilters;` dan membentuk periode dengan `Periode::dariFilter($this->pageFilters)`.

```
Widget: KelengkapanDataStats — Filament\Widgets\StatsOverviewWidget — Location: Dasbor (admin)
  Docs: https://filamentphp.com/docs/5.x/widgets/stats-overview
  Heading: 'Kelengkapan data'
  Stats (semua pelanggan aktif KSM ini, Stat dari Filament\Widgets\StatsOverviewWidget\Stat):
    Stat 'Tanpa RT/RW': whereNull('wilayah_id')->count() — Description: 'tetapkan di menu Pelanggan (filter "Tanpa RT")' / 'semua sudah ber-RT'
         — Color: >0 warning : success — Icon: Heroicon::OutlinedMapPin — Url: PelangganResource::getUrl('index')
    Stat 'Tanpa nomor HP': ->where(fn ($q) => $q->whereNull('user_id')->orWhereHas('akunWarga', fn ($u) => $u->whereNull('no_hp')))->count()
         — Color: >0 warning : success — Icon: Heroicon::OutlinedPhone
    Stat 'Tanpa lokasi peta': whereNull('latitude')->whereNull('alamat')->count() — Color: >0 gray : success — Icon: Heroicon::OutlinedMap
  (5B: tambah Stat 'Transaksi tanpa bukti' — TransaksiKas bulan berjalan whereNull('bukti') — Color warning/success — Url: TransaksiKasResource::getUrl('index'))

Widget: OperasionalPeriodeStats — StatsOverviewWidget + InteractsWithPageFilters — Location: Analitik › Operasional
  Stats (periode P, RingkasanPengangkutan $r):
    Stat 'RT terlayani': "{x} dari {y} RT" dari $r->rtTerlayani(P) — Description: y = 0 ? 'isi daftar RT di Data Master' : 'RT dengan ≥1 rumah diangkut' — Icon: Heroicon::OutlinedMap — Color: primary
    Stat 'Cakupan layanan': FormatAngka::Persen->tampil($r->cakupanKsm(P)) — Description: '{terlayani} dari {aktif} rumah aktif diangkut ≥1×' — Color: §0.2 warna indikator
    Stat 'Hari pengangkutan': $r->hariPengangkutan(P) — Description: 'rata-rata {n} kunjungan/rumah/minggu' (dari baris total perWilayah) — Icon: Heroicon::OutlinedCalendarDays
    Stat 'Petugas aktif': petugas KSM aktif count — Description: '{n} bertugas di periode ini' (perPetugas hari_bertugas > 0) — Icon: Heroicon::OutlinedUserGroup
    Stat 'Perkiraan sampah': RingkasanPengangkutan::kg($r->berat(P->dariString(), P->sampaiString())) — Icon: Heroicon::Scale — Color: success

Widget: BeratTerangkutChart — Filament\Widgets\ChartWidget + InteractsWithPageFilters — Location: Analitik › Operasional
  Docs: https://filamentphp.com/docs/5.x/widgets/charts
  ChartType: bar — Heading: 'Perkiraan sampah terangkut' — Description: P->label() — ColumnSpan: full — MaxHeight: 280px
  Data: P->bulan !== null → per hari P->hari() (kg, hari tanpa ronde = 0; label 'd M'); setahun → $r->beratPerBulan(P) (label 'M')

Widget: TabelRealisasiPerRt — TabelLaporanWidget — Location: Analitik › Operasional & Laporan › Operasional — tabel: LaporanOperasional::perWilayah
Widget: TabelKinerjaPetugas — TabelLaporanWidget — Location: Laporan › Operasional — tabel: LaporanOperasional::perPetugas
Widget: TabelRekapHarian — TabelLaporanWidget — Location: Laporan › Operasional — tabel: LaporanOperasional::perHari

Widget: PengangkutanTerakhirTable — Filament\Widgets\TableWidget + InteractsWithPageFilters — Location: Analitik › Operasional
  Docs: https://filamentphp.com/docs/5.x/widgets/overview#table-widgets
  Heading: 'Kegiatan terakhir'
  Query: fn () => Pengangkutan::query()->whereBelongsTo(Filament::getTenant())->whereDate('tanggal', '>=', P->dariString())->whereDate('tanggal', '<=', P->sampaiString())
           ->with('petugas')->withCount(['rincian', 'rincian as diangkut_count' => fn ($q) => $q->where('status', StatusPengangkutan::Diangkut)])
           ->withSum('rincian as kg', 'perkiraan_kg')->latest('tanggal')
  Table Config: ->paginated([5, 10])->defaultPaginationPageOption(5)
  Columns (Filament\Tables\Columns\TextColumn): tanggal ->date('D, d M Y'); petugas.name ->label('Petugas'); rincian_count ->label('Rumah dicatat');
           diangkut_count ->label('Diangkut'); kg ->numeric(decimalPlaces: 1, locale: 'id')->suffix(' kg')->placeholder('—')
  Record URL: fn (Pengangkutan $r) => PengangkutanResource::getUrl('edit', ['record' => $r])   (resource memeriksa kebijakan sendiri)
```
Widget lama tidak berubah (`IuranStatsOverview`, `IuranPerBulanChart`, `OperasionalStatsOverview`, `BeratPerHariChart`, widget petugas & warga).

### 5.7 Halaman (5A)
Notasi `$routePath = $slug = 'x'` = kedua properti statis diisi string yang sama (§4.3), bukan penugasan berantai di PHP.
```
Page: App\Filament\Ksm\Pages\Analitik\AnalitikOperasional — extends HalamanAnalitik
  Docs: https://filamentphp.com/docs/5.x/widgets/overview#creating-multiple-dashboards
  $routePath = $slug = 'analitik/operasional'; $title = 'Analitik Operasional'; $navigationLabel = 'Operasional'
  Navigation: group 'Analitik', icon Heroicon::OutlinedTruck, sort 20
  getWidgets(): [OperasionalPeriodeStats, BeratTerangkutChart, TabelRealisasiPerRt, PengangkutanTerakhirTable]

Page: App\Filament\Ksm\Pages\Laporan\LaporanOperasionalPage — extends HalamanLaporan
  $routePath = $slug = 'laporan/operasional'; $title = 'Laporan Operasional'; $navigationLabel = 'Operasional'
  Navigation: group 'Laporan', icon Heroicon::OutlinedClipboardDocumentList, sort 20
  jenisLaporan(): 'operasional' — pembangun(): new LaporanOperasional(Filament::getTenant())
  getWidgets(): [TabelRealisasiPerRt, TabelKinerjaPetugas, TabelRekapHarian]
```
`RekapIuran` (UBAH): `$navigationSort = 10`; tambah header action **`unduhExcel`** (Component `Filament\Actions\Action`, Location header di samping pemilih tahun,
Authorization `isAdminKsm()`, Config label 'Unduh Excel' icon `Heroicon::OutlinedArrowDownTray` color gray). Behavior: (1) `$pelanggan = $this->getFilteredTableQuery()->orderBy('nomor_urut')->get()`
(`vendor/filament/tables/src/Concerns/HasRecords.php:32` — menghormati filter "aktif" di layar); (2) bentuk `TabelLaporan` "Rekap Iuran {tahun}": kolom No · Angka, Nama · Teks, Iuran/bln · Rupiah,
satu kolom per bulan `bulanDitampilkan()` · Rupiah, Total · Rupiah; nilai dari `$this->pembayaran`; baris total = Σ per kolom; (3) `EksporXlsx::unduh('iuran', 'Rekap Iuran', $ksm, Periode::dariFilter(['tahun' => $this->tahun, 'bulan' => 'semua']), [$tabel])`.

### 5.8 Panel master (5A) — `app/Filament/Widgets/` (tidak ber-tenant: query lintas KSM disengaja)
Semua `canView()` → `(bool) auth()->user()?->is_super_admin` (lapis kedua; panel `admin` sudah hanya untuk super admin, `User::canAccessPanel`).
`AdminPanelProvider`: hapus `FilamentInfoWidget::class` dari `->widgets()`.
```
Widget: RingkasanMasterStats — StatsOverviewWidget — sort 1 — Docs: https://filamentphp.com/docs/5.x/widgets/stats-overview
  $sejak = now()->subDays(7); $bulan = Hari::ini()->startOfMonth()
  Stat 'KSM terdaftar': Ksm::count() — Description: Ksm::query()->aktifMencatat($sejak)->count().' aktif mencatat 7 hari terakhir' — Icon: Heroicon::OutlinedBuildingOffice2 — Color: primary
  Stat 'Pelanggan aktif': Pelanggan::where('aktif', true)->count() — Description: 'dari {Pelanggan::count()} terdaftar' — Icon: Heroicon::OutlinedHomeModern
  Stat 'Iuran {bulan F Y}': Σ PembayaranIuran whereDate('periode', $bulan) nominal — Description: 'target Rp… · x%' (target = Σ iuran_bulanan pelanggan aktif semua KSM) — Icon: Heroicon::OutlinedBanknotes
  Stat 'Di tangan petugas': Σ PembayaranIuran::belumDisetor() nominal — Color: >0 warning : success — Icon: Heroicon::OutlinedWallet
  Stat 'Sampah 30 hari': Σ perkiraan_kg rincian dari pengangkutan tanggal ≥ Hari::tanggal(29) — Format: RingkasanPengangkutan::kg — Icon: Heroicon::Scale

Widget: RingkasanPerKsmTable — TableWidget — sort 2 — columnSpan full — Heading 'Per KSM'
  Query: fn () => Ksm::query()
     ->withCount(['pelanggan as pelanggan_aktif_count' => fn ($q) => $q->where('aktif', true)])
     ->withSum(['pembayaranIuran as iuran_bulan_ini' => fn ($q) => $q->whereDate('periode', $bulan)], 'nominal')
     ->withSum(['pelanggan as target' => fn ($q) => $q->where('aktif', true)], 'iuran_bulanan')
     ->withSum(['pembayaranIuran as di_tangan_petugas' => fn ($q) => $q->belumDisetor()], 'nominal')
     ->addSelect(['kg_30_hari' => PengangkutanRincian::query()->selectRaw('coalesce(sum(perkiraan_kg), 0)')
          ->join('pengangkutan', 'pengangkutan.id', '=', 'pengangkutan_rincian.pengangkutan_id')
          ->whereColumn('pengangkutan.ksm_id', 'ksm.id')->whereDate('pengangkutan.tanggal', '>=', Hari::tanggal(29))])
     ->withMax('pengangkutan', 'created_at')->withMax('pembayaranIuran', 'created_at')->withMax('setoran', 'created_at')
  Table Config: ->paginated(false)->defaultSort('nama')
  Columns (TextColumn):
    nama — ->description(fn (Ksm $r) => "Desa {$r->desa}")->url(fn (Ksm $r) => $r->url(), shouldOpenInNewTab: true)->searchable()
    pelanggan_aktif_count — ->label('Pelanggan aktif')->numeric()
    iuran_bulan_ini — ->label('Iuran '.$bulan F)->money('IDR', locale: 'id', decimalPlaces: 0)->description(fn ($r) => target > 0 ? round(iuran/target*100).'% target' : null)
    kg_30_hari — ->label('Sampah 30 hari')->numeric(decimalPlaces: 1, locale: 'id')->suffix(' kg')
    di_tangan_petugas — ->label('Di tangan petugas')->money('IDR', locale: 'id', decimalPlaces: 0)->color(fn ($state) => $state > 0 ? 'warning' : null)
    catatan_terakhir — ->label('Catatan terakhir')->state(fn (Ksm $r) => max of non-null withMax kolom)->dateTime('d M Y H:i', timezone: config('app.zona_waktu_lokal'))->placeholder('belum pernah')
    status — ->badge()->state(fn (Ksm $r): string => in_array($r->id, $this->aktifIds()) ? 'Aktif mencatat' : 'Belum aktif')->color(fn (string $s) => $s === 'Aktif mencatat' ? 'success' : 'danger')
       ($this->aktifIds() = Ksm::query()->aktifMencatat($sejak)->pluck('id')->all(), di-memo per request — satu aturan §3.9)
  Filters: Filament\Tables\Filters\Filter::make('belum_aktif') — Docs: https://filamentphp.com/docs/5.x/tables/filters/custom —
    Config: ->label('Belum aktif mencatat (7 hari)')->toggle()->query(fn (Builder $q) => $q->whereNot(fn ($w) => $w->aktifMencatat(now()->subDays(7))))
  Record actions: Filament\Actions\Action::make('edit') ->url(fn (Ksm $r) => KsmResource::getUrl('edit', ['record' => $r]))->icon(Heroicon::OutlinedPencilSquare) (resource master memeriksa sendiri)

Widget: IuranSemuaKsmChart — ChartWidget — sort 3 — bar — Heading 'Iuran semua KSM per bulan' — 12 bulan s.d. bulan berjalan, Σ nominal per `periode`
```

### 5.9 Authorization (5A)
| Policy / Page | viewAny | view | create | update | delete | deleteAny |
|---|---|---|---|---|---|---|
| `WilayahPolicy` (baru) | admin | admin | admin | admin | admin ∧ `! dipakai()` | false |
| `AnalitikOperasional`, `LaporanOperasionalPage` | `canAccess()`: admin | | | | | |
| Widget analitik/laporan desa | `canView()`: admin (Filament memeriksa sebelum mount **dan** sebelum update Livewire → 403) | | | | | |
| Widget master | `canView()`: `is_super_admin` | | | | | |
| `RekapIuran::unduhExcel` | `authorize`: admin | | | | | |
"admin" = `isAdminKsm()` (admin desa atau administrator pusat). Petugas & warga: halaman 403, menu tak tampil.

### 5.10 Tests 5A (PHPUnit, `Livewire::test`, `travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'))`, `diKsmLain`)
`PeriodeTest`:
1. Tanpa filter pada **2026-11-30 23:30 WITA** → November; pada **2026-12-01 07:00 WITA** (= 30 Nov 23:00 UTC) → **Desember** (hari desa, bukan UTC).
2. `['tahun' => '2026', 'bulan' => 'semua']` → 2026-01-01 … 2026-12-31, label "Tahun 2026".
3. Data rusak — `tahun` `'abc'`/`1999`/`['x']`/`'2028'` (di 2026), `bulan` `'13'`/`'0'`/`'semua '`/`['1']` → default, **tanpa exception**.
4. `jumlahHari()`: Nov 2026 pada 17 Nov → 17; Okt 2026 → 31; Des 2026 → 0. `sebelumnya()`: Jan 2026 → Des 2025; tahun 2026 → 2025.

`WilayahTest`:
5. Admin membuat RT 1/RW 2 → `ksm_id` = tenant. RT 1/RW 2 lagi → error `rt` unique; RT 1 tanpa RW dua kali → error pada yang kedua (cabang `whereNull`); RT 1/RW 2 ada di KSM lain (`diKsmLain`) → tetap boleh di KSM ini.
6. Form Pelanggan: opsi `wilayah_id` = tepat wilayah KSM ini (bukan KSM lain); kirim id wilayah KSM lain → error in-rule; simpan pelanggan tanpa perubahan → `wilayah_id` tetap.
7. RT berpelanggan: `DeleteAction` tersembunyi dan `can('delete')` false; RT kosong → terhapus.
8. Petugas & warga → 403 di `/wilayahs`.
9. Hapus KSM yang punya wilayah + pelanggan ber-wilayah → berhasil (regresi jebakan cascade).

`LaporanOperasionalTest` — fixture asimetris (hari ini 2026-11-17; KSM A: RT1 `jumlah_kk` 10, RT2 tanpa pelanggan; p1 & p2 aktif RT1, p3 **nonaktif** RT1, p4 aktif tanpa RT;
ronde petugas A 3 Nov: p1 diangkut 2,0 · p4 diangkut 1,5; ronde A 10 Nov: p1 3,0 · p2 tidak bisa diakses · p3 diangkut 5,0; ronde petugas B 17 Nov: p1 4,0;
ronde A 31 Okt: p2 diangkut 50,0; KSM B ronde 10 Nov 99,0). Periode November 2026:
10. Per RT: RT1 aktif 2, terlayani 1, cakupan 50 %, penetrasi 20 %, kunjungan 3, rata-rata **0,6** (= 3/2/(17/7)), tidak bisa diakses 1, 9,0 kg;
    RT2 aktif 0, cakupan "—"; Tanpa RT aktif 1, terlayani 1, 100 %, 1,5 kg; Pelanggan nonaktif kunjungan 1, 5,0 kg.
    **Total kg 15,5 = `RingkasanPengangkutan::berat('2026-11-01', '2026-11-30')`** (bukan 65,5 dengan Oktober, bukan 114,5 dengan KSM B).
11. `OperasionalPeriodeStats`: "1 dari 2 RT", cakupan 67 % (2 dari 3), hari pengangkutan 3.
12. Kinerja petugas: A hari 2, dicatat 5, diangkut 4, 11,5 kg; B hari 1, dicatat 1, diangkut 1, 4,0 kg; iuran diterima A: pembayaran `dibayar_pada` 5 Nov Rp50.000 dihitung, 30 Okt Rp25.000 tidak; petugas KSM B tidak tercantum.
13. Excel: `callAction('unduhExcel')` → `assertFileDownloaded('laporan-operasional-makmurjaya-2026-11.xlsx')`; konten
    (`base64_decode($testable->effects['download']['content'])` **[cek]** akses `effects`, `Testable.php:382`) dibaca dengan `OpenSpout\Reader\XLSX\Reader`:
    3 sheet; sel kg RT1 **numerik** 9.0; total 15.5 — sama dengan layar.
14. Filter rusak `Livewire::test(LaporanOperasionalPage::class, ['filters' => ['tahun' => 'x', 'bulan' => 99]])` → 200, subjudul "Periode: November 2026".
15. Aksi `cetak` ada dengan `getAlpineClickHandler() === 'window.print()'`; HTML halaman memuat `ksm-kop-cetak` dengan nama KSM.
16. Petugas & warga: `/analitik/operasional`, `/laporan/operasional` → 403; `Livewire::test(TabelRealisasiPerRt::class)` sebagai petugas → 403.
17. Dasbor admin **tidak** memuat `TabelRealisasiPerRt`/`OperasionalPeriodeStats` (`assertDontSeeLivewire`) tetapi memuat `KelengkapanDataStats`; dasbor petugas tetap `TugasPetugasStatsOverview` + `DaftarTugasHariIni`.
18. Rekap Iuran Excel: dua pelanggan (satu nonaktif dengan pembayaran), filter `aktif = true` → berkas hanya berisi yang aktif; total = Σ baris.

`DasborMasterTest`: KSM A (pembayaran dibuat kemarin), B (catatan terakhir 10 hari lalu), C (tanpa catatan):
19. Stat "KSM terdaftar 3" + "1 aktif mencatat"; badge A "Aktif mencatat", B & C "Belum aktif"; filter `belum_aktif` → tepat B, C.
20. Iuran bulan ini: A Rp50.000 periode Nov dihitung, B Rp25.000 periode Okt tidak; di tangan petugas = Σ belum disetor semua KSM.
21. Admin desa (bukan super admin) → `RingkasanMasterStats::canView()` false dan `/admin` 403.

**Sabotase wajib** (masing-masing harus membuat tes merah): syarat `aktif` di `terlayani` (→ RT1 100 %); batas periode di rincian (→ Oktober ikut);
`whereBelongsTo` di `perWilayah` (→ KSM B ikut); sanitasi `Periode` (→ exception); `$columnSpan`/`canView` admin pada widget laporan; batas 7 hari master (ubah ke 70 → B aktif).

---

## 6. Fase 5B — Buku kas & mitra

### 6.1 Enum
```
Enum: App\Enums\JenisTransaksi: string — Implements: HasLabel, HasColor, HasIcon
  Masuk = 'masuk': label 'Pemasukan', color 'success', icon Heroicon::OutlinedArrowDownCircle
  Keluar = 'keluar': label 'Pengeluaran', color 'danger', icon Heroicon::OutlinedArrowUpCircle
  bolehMitra(): bool → $this === self::Masuk
  static dari(mixed $nilai): ?self → $nilai instanceof self ? $nilai : self::tryFrom((string) $nilai) — state `Get` bisa berupa
    instans enum (dihidrasi dari model ber-cast) atau string (dari klien); **semua** kondisi form memakai helper ini, jangan
    `(string) $get('jenis')` (cast enum ke string melempar Error) atau `=== 'masuk'` (gagal untuk instans enum). Sama untuk AktivitasMaterial.

Enum: App\Enums\JenisMitra: string — Implements: HasLabel, HasColor, HasIcon
  Pemerintah = 'pemerintah': 'Pemerintah', 'info', Heroicon::OutlinedBuildingLibrary
  Swasta = 'swasta': 'Swasta / CSR', 'primary', Heroicon::OutlinedBuildingOffice2
  Pengepul = 'pengepul': 'Pengepul / bank sampah', 'warning', Heroicon::OutlinedArrowPathRoundedSquare
  Komunitas = 'komunitas': 'Komunitas / LSM / sekolah', 'success', Heroicon::OutlinedUserGroup
  Lainnya = 'lainnya': 'Lainnya', 'gray', Heroicon::OutlinedEllipsisHorizontalCircle
```

### 6.2 Models
```
Model: KategoriTransaksi   (#[Table('kategori_transaksi')], #[Fillable(['jenis', 'nama', 'aktif'])])
  Attributes: id; ksm_id foreign(ksm.id) cascadeOnDelete; jenis string(10) required (JenisTransaksi);
              nama string(100) required; aktif boolean default:true; timestamps
  Index: unique(ksm_id, jenis, nama)
  Casts: jenis JenisTransaksi, aktif boolean
  Relationships: belongsTo Ksm; hasMany TransaksiKas via kategori_transaksi_id
  Methods: dipakai(): bool → transaksiKas()->exists()
  Const: NAMA_SISTEM = ['iuran', 'penjualan material']

Model: Mitra   (#[Table('mitra')], #[Fillable(['nama', 'jenis', 'kontak', 'no_hp', 'alamat', 'keterangan', 'aktif'])])
  Attributes: id; ksm_id foreign cascadeOnDelete; nama string(150) required; jenis string(20) required (JenisMitra);
              kontak string(100) nullable (narahubung); no_hp string(20) nullable (ternormalisasi NomorHp);
              alamat text nullable; keterangan text nullable; aktif boolean default:true; timestamps
  Index: unique(ksm_id, nama)
  Casts: jenis JenisMitra, aktif boolean
  Relationships: belongsTo Ksm; hasMany TransaksiKas via mitra_id; (5C) hasMany CatatanMaterial via mitra_id
  Methods: dipakai(): bool → transaksiKas()->exists() (5C: || catatanMaterial()->exists())

Model: TransaksiKas   (#[Table('transaksi_kas')], #[Fillable(['tanggal', 'jenis', 'kategori_transaksi_id', 'mitra_id', 'nominal', 'uraian', 'bukti'])])
  Attributes: id; ksm_id foreign cascadeOnDelete; tanggal date required; jenis string(10) required (JenisTransaksi);
              kategori_transaksi_id foreign(kategori_transaksi.id) nullable nullOnDelete;
              mitra_id foreign(mitra.id) nullable nullOnDelete;
              nominal unsignedBigInteger required; uraian string(255) required; bukti string nullable (path disk local);
              dicatat_oleh_id foreign(users.id) nullable nullOnDelete; timestamps
  Index: (ksm_id, tanggal), (ksm_id, jenis, tanggal)
  Casts: tanggal date, jenis JenisTransaksi, nominal integer
  Relationships: belongsTo Ksm; belongsTo KategoriTransaksi (kategori); belongsTo Mitra; belongsTo User (dicatatOleh) via dicatat_oleh_id
  Hooks: saving → bila ! $jenis->bolehMitra(): mitra_id = null
         updated → bila wasChanged('bukti') && getOriginal('bukti') terisi: Storage::disk('local')->delete(lama)
         deleted → bila bukti terisi: Storage::disk('local')->delete(bukti)
  Methods: buktiUrl(): ?string → filled(bukti) ? Storage::disk('local')->temporaryUrl($this->bukti, now()->addMinutes(30)) : null

Migration add_buku_kas_to_ksm_table (ksm):
  Add: saldo_awal unsignedBigInteger default:0 (after sandi_default_hash)
  Add: kas_mulai date nullable
Ksm: Fillable + 'saldo_awal', 'kas_mulai'; casts saldo_awal integer, kas_mulai date;
     relasi kategoriTransaksi(), mitra(), transaksiKas() (HasMany, nama = $tenantRelationshipName resource);
     RELASI_CATATAN + 'transaksiKas'
PembayaranIuran: scope diterimaAntara + tanggalKas() (§3.2) — scope sudah dibuat di 5A bila dipakai kinerja petugas
```
Factories: `KategoriTransaksiFactory` (jenis keluar, nama `fake()->unique()->words(2, true)`), `MitraFactory` (jenis swasta), `TransaksiKasFactory` (keluar, nominal 50.000, uraian kalimat; state `masuk()`).

### 6.3 `App\Support\RingkasanKas` (BARU) — §3.2–3.3
```php
public function __construct(private readonly Ksm $ksm) {}
public function iuran(string $dari, string $sampai): int          // PembayaranIuran::whereBelongsTo->diterimaAntara()->sum('nominal')
public function masukManual(string $dari, string $sampai): int    // TransaksiKas masuk, whereDate tanggal antara
public function penjualanMaterial(string $dari, string $sampai): int  // 5B: return 0; 5C: RingkasanMaterial terjual nilai
public function masuk(string $dari, string $sampai): int           // iuran + masukManual + penjualanMaterial
public function keluar(string $dari, string $sampai): int
public function pemasukanPerSumber(string $dari, string $sampai): array  // ['Iuran' => …, ('Penjualan material' => … 5C), {kategori nama} => …, 'Tanpa kategori' => …]; nilai 0 dibuang
public function pengeluaranPerKategori(string $dari, string $sampai): array
public function saldoAkhir(string $tanggal): ?int
public function saldoAwal(string $tanggal): ?int
public function diTanganPetugas(): int                              // PembayaranIuran::belumDisetor (snapshot)
/** @return list<array{bulan: string, saldo_awal: ?int, iuran: int, material: int, masuk_lain: int, keluar: int, surplus: int, saldo_akhir: ?int}> */
public function perBulan(array $bulan): array                       // $bulan = list<CarbonImmutable> awal bulan: Periode::bulanBulan() (laporan) atau Periode::duaBelasBulanSampai() (grafik tren)
```
Semua query `whereBelongsTo($this->ksm)`; tanggal dibandingkan sebagai string `Y-m-d`.

### 6.4 Resource `KategoriTransaksiResource` (BARU)
- Location: `app/Filament/Ksm/Resources/KategoriTransaksis/KategoriTransaksiResource.php` + `Pages/ManageKategoriTransaksis.php` (pola Tarif).
  `$tenantRelationshipName = 'kategoriTransaksi'`, grup 'Data Master', sort 30, icon `Heroicon::OutlinedRectangleStack`, label 'kategori transaksi'.
- `getEloquentQuery()`: admin → `withCount('transaksiKas')`; lainnya `whereRaw('1 = 0')`.
- **Form** (columns 1):
  - Field `jenis` — Component: `Filament\Forms\Components\ToggleButtons` — Docs: https://filamentphp.com/docs/5.x/forms/toggle-buttons —
    Validation: required, enum (opsi) — Config: `->options(JenisTransaksi::class)->inline()->required()->default(JenisTransaksi::Keluar->value)->disabled(fn (?KategoriTransaksi $record): bool => (bool) $record?->dipakai())->helperText(fn (?KategoriTransaksi $record): ?string => $record?->dipakai() ? 'Sudah dipakai transaksi — jenis tidak bisa diubah.' : null)`
    (field `disabled` tidak didehidrasi → nilai tersimpan tidak bisa diubah dari klien).
  - Field `nama` — `Filament\Forms\Components\TextInput` — Docs: https://filamentphp.com/docs/5.x/forms/text-input —
    Validation: required, max:100, unik per (KSM, jenis), bukan nama sistem —
    Imports: `Filament\Schemas\Components\Utilities\Get` —
    Config: `->required()->maxLength(100)->placeholder('mis. BBM & perawatan kendaraan')->scopedUnique(ignoreRecord: true, modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('jenis', JenisTransaksi::dari($get('jenis'))))`
    (field `jenis` yang disabled tetap memegang nilai tersimpan di state, jadi `$get('jenis')` benar juga untuk kategori terpakai)
    + penolakan nama sistem tidak peka huruf besar: `->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void { if (in_array(mb_strtolower(trim((string) $value)), KategoriTransaksi::NAMA_SISTEM, true)) { $fail('"Iuran" dan "Penjualan material" sudah dihitung otomatis — pakai nama lain.'); } })`.
  - Field `aktif` — `Filament\Forms\Components\Toggle` — Docs: https://filamentphp.com/docs/5.x/forms/toggle — Config: `->default(true)->helperText('Kategori nonaktif tidak bisa dipilih untuk transaksi baru.')`.
- **Table**: `nama` (searchable), `jenis` (`->badge()`), `transaksi_kas_count` ->label('Transaksi'), `aktif` (`Filament\Tables\Columns\IconColumn` `->boolean()`, Docs: https://filamentphp.com/docs/5.x/tables/columns/icon);
  Filters: `SelectFilter::make('jenis')->options(JenisTransaksi::class)`, `TernaryFilter::make('aktif')` (Docs: https://filamentphp.com/docs/5.x/tables/filters/ternary).
  Actions: `ActionGroup::make([EditAction::make(), DeleteAction::make()])` — Delete via kebijakan (§3.7). Header `CreateAction`.

### 6.5 Resource `MitraResource` (BARU)
- Location: `app/Filament/Ksm/Resources/Mitras/MitraResource.php` + `Pages/ManageMitras.php`; `$tenantRelationshipName = 'mitra'`, grup 'Data Master', sort 40, icon `Heroicon::OutlinedHandRaised`, label 'mitra'.
- `getEloquentQuery()`: admin → `withSum(['transaksiKas as dukungan' => fn ($q) => $q->where('jenis', JenisTransaksi::Masuk)], 'nominal')`; lainnya `1 = 0`.
- **Form** (columns 2):
  - `nama` — TextInput — Validation: required, max:150, scopedUnique — Config: `->required()->maxLength(150)->scopedUnique(ignoreRecord: true)->columnSpanFull()`.
  - `jenis` — Select — Validation: required, enum — Config: `->options(JenisMitra::class)->required()->native(false)`.
  - `aktif` — Toggle — Config: `->default(true)->inline(false)`.
  - `kontak` — TextInput — Validation: nullable, max:100 — Config: `->label('Narahubung')->maxLength(100)`.
  - `no_hp` — TextInput — Validation: nullable, format `NomorHp::valid` setelah normalisasi —
    Config: `->label('Nomor HP')->tel()->maxLength(20)->dehydrateStateUsing(fn (?string $state): ?string => NomorHp::normalisasi($state))->rule(fn (): Closure => function (string $a, mixed $v, Closure $fail): void { if (filled($v) && ! NomorHp::valid(NomorHp::normalisasi($v))) { $fail('Nomor HP tidak valid (contoh 081234567890).'); } })`.
    Normalisasi & validasi memakai fungsi yang sama (aturan README §4).
  - `alamat`, `keterangan` — `Filament\Forms\Components\Textarea` (Docs: https://filamentphp.com/docs/5.x/forms/textarea) — Validation: nullable, max:1000 — Config: `->rows(2)->maxLength(1000)->columnSpanFull()`.
- **Table**: `nama` (searchable, description `kontak · no_hp`), `jenis` badge, `dukungan` ->label('Dukungan diterima')->money('IDR', locale: 'id', decimalPlaces: 0)->placeholder('—'), `aktif` IconColumn boolean.
  Filters: `SelectFilter jenis` (enum), `TernaryFilter aktif` (`->default(true)`). Actions: ActionGroup [Edit, Delete (kebijakan §3.7)]. Header CreateAction.

### 6.6 Resource `TransaksiKasResource` — "Buku Kas" (BARU)
- Location: `app/Filament/Ksm/Resources/TransaksiKas/TransaksiKasResource.php` + `Pages/ManageTransaksiKas.php`.
  `$tenantRelationshipName = 'transaksiKas'`, grup 'Data Input', sort 40, icon `Heroicon::OutlinedBookOpen`,
  `$navigationLabel = 'Buku Kas'`, label 'transaksi kas'.
- `getEloquentQuery()`: admin → `with(['kategori', 'mitra', 'dicatatOleh'])`; lainnya `1 = 0`.
- **Form** (`$schema->columns(2)`), Imports: `Filament\Schemas\Components\Utilities\Get`, `Filament\Schemas\Components\Utilities\Set`:
  - Field `jenis` — `Filament\Forms\Components\ToggleButtons` — Validation: required, enum —
    Config: `->options(JenisTransaksi::class)->inline()->required()->default(JenisTransaksi::Keluar->value)->live()->afterStateUpdated(function (Set $set): void { $set('kategori_transaksi_id', null); $set('mitra_id', null); })->columnSpanFull()`.
  - Field `tanggal` — `Filament\Forms\Components\DatePicker` — Docs: https://filamentphp.com/docs/5.x/forms/date-time-picker —
    Validation: required, ≤ hari ini (desa) — Config: `->native(false)->displayFormat('d M Y')->default(fn (): string => Hari::tanggal())->maxDate(fn (): string => Hari::tanggal())->required()`.
  - Field `nominal` — TextInput — Validation: required, integer, min:1, max:1000000000 — Config: `->numeric()->integer()->prefix('Rp')->minValue(1)->maxValue(1_000_000_000)->required()`.
  - Field `kategori_transaksi_id` — Select — Validation: required, `in` dari opsi —
    Config: `->label('Kategori')->options(fn (Get $get, ?TransaksiKas $record): array => PilihanMaster::kategori(JenisTransaksi::dari($get('jenis')), $record?->kategori_transaksi_id))->required()->helperText(fn (Get $get): ?string => PilihanMaster::kategori(JenisTransaksi::dari($get('jenis'))) === [] ? 'Belum ada kategori — tambahkan di Data Master → Kategori Transaksi.' : null)`.
    `PilihanMaster::kategori(?JenisTransaksi $jenis, ?int $tersimpan = null)`: kategori KSM ini dengan `jenis` itu dan `aktif`, **plus** `$tersimpan` hanya bila jenisnya sama; `$jenis` null → `[]`. Label nonaktif diberi " (nonaktif)".
  - Field `mitra_id` — Select — Validation: nullable, `in` — Config: `->label('Dari mitra (opsional)')->options(fn (?TransaksiKas $record): array => PilihanMaster::mitra($record?->mitra_id))->searchable()->visible(fn (Get $get): bool => JenisTransaksi::dari($get('jenis'))?->bolehMitra() ?? false)`
    (`visible(false)` → tidak divalidasi & tidak didehidrasi; hook model menolak mitra pada pengeluaran sebagai lapis kedua).
  - Field `uraian` — TextInput — Validation: required, max:255 — Config: `->required()->maxLength(255)->placeholder('mis. Beli bensin motor angkut')->columnSpanFull()`.
  - Field `bukti` — `Filament\Forms\Components\FileUpload` — Docs: https://filamentphp.com/docs/5.x/forms/file-upload —
    Validation: nullable, mimetypes jpeg/png/webp/pdf, max 4096 KB —
    Config: `->label('Bukti (foto/PDF)')->disk('local')->directory('bukti-kas')->visibility('private')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(4096)->openable()->columnSpanFull()`
    (`preventFilePathTampering()` sudah global, README §6).
- **Table** — `->defaultSort('tanggal', 'desc')`:
  - `tanggal` TextColumn `->date('d M Y')->sortable()`.
  - `jenis` TextColumn `->badge()`.
  - `kategori.nama` TextColumn `->label('Kategori')->placeholder('Tanpa kategori')`.
  - `uraian` TextColumn `->limit(50)->searchable()->description(fn (TransaksiKas $r): ?string => $r->mitra?->nama)`.
  - `nominal` TextColumn `->money('IDR', locale: 'id', decimalPlaces: 0)->color(fn (TransaksiKas $r): string => $r->jenis->getColor())->sortable()->summarize([...])` —
    Docs: https://filamentphp.com/docs/5.x/tables/summaries — dua `Filament\Tables\Columns\Summarizers\Summarizer::make()`:
    `->label('Pemasukan')->using(fn (\Illuminate\Database\Query\Builder $q): int => (int) (clone $q)->where('jenis', 'masuk')->sum('nominal'))` dan `->label('Pengeluaran')` (keluar), format `RingkasanIuran::rupiah`.
  - `bukti` `Filament\Tables\Columns\IconColumn` `->label('Bukti')->state(fn (TransaksiKas $r): bool => filled($r->bukti))->boolean()->trueIcon(Heroicon::OutlinedPaperClip)->falseIcon(Heroicon::OutlinedMinus)->url(fn (TransaksiKas $r): ?string => $r->buktiUrl(), shouldOpenInNewTab: true)`.
  - `dicatatOleh.name` `->label('Dicatat oleh')->toggleable(isToggledHiddenByDefault: true)`.
- **Filters**: `SelectFilter jenis` (enum); `SelectFilter kategori_transaksi_id` `->options(fn () => PilihanMaster::semuaKategori())` (eksplisit tenant, termasuk nonaktif);
  `SelectFilter mitra_id` `->options(fn () => PilihanMaster::mitra())`; `Filter::make('tanggal')` (Docs: https://filamentphp.com/docs/5.x/tables/filters/custom)
  `->schema([DatePicker::make('dari'), DatePicker::make('sampai')])->query(fn (Builder $q, array $data) => $q->when($data['dari'], fn ($q, $d) => $q->whereDate('tanggal', '>=', $d))->when($data['sampai'], fn ($q, $d) => $q->whereDate('tanggal', '<=', $d)))`.
- **Actions**:
  - Header `Filament\Actions\CreateAction` — Docs: https://filamentphp.com/docs/5.x/actions/create — Location: header ManageTransaksiKas —
    Visibility/Authorization: `TransaksiKasPolicy::create` — Behavior: `->using(function (array $data): TransaksiKas { $t = new TransaksiKas($data); $t->forceFill(['dicatat_oleh_id' => auth()->id()]); Filament::getTenant()->transaksiKas()->save($t); return $t; })`
    (`using` menggantikan penyimpanan bawaan `CreateAction`, `vendor/filament/actions/src/CreateAction.php:66`; asosiasi tenant dilakukan sendiri seperti `CreateSetoran`).
  - Record `ActionGroup::make([EditAction::make(), DeleteAction::make()->modalDescription('Transaksi dan berkas buktinya akan dihapus.')])` — Authorization: kebijakan.

### 6.7 Profil KSM (UBAH) — `EditProfilKsm`
Tambah `Section::make('Buku kas')->description('Saldo kas = saldo awal + semua pemasukan − pengeluaran sejak tanggal mulai pembukuan.')->columns(2)->schema([...])`:
- Field `saldo_awal` — TextInput — Validation: required, integer, min:0, max:1000000000000 — Config: `->label('Saldo kas awal')->numeric()->integer()->prefix('Rp')->minValue(0)->maxValue(1_000_000_000_000)->default(0)->required()`.
- Field `kas_mulai` — DatePicker — Validation: nullable, ≤ hari ini — Config: `->label('Mulai pembukuan')->native(false)->displayFormat('d M Y')->maxDate(fn (): string => Hari::tanggal())->helperText('Uang yang ada di kas pada pagi hari tanggal ini = saldo awal. Kosongkan untuk menghitung dari catatan pertama.')`.

### 6.8 `LaporanKeuangan` (BARU)
| Method | Judul | Kolom | Total |
|---|---|---|---|
| `arusKasBulanan(Periode)` | Arus kas bulanan | bulan · Bulan · Teks (`F Y`); saldo_awal · Saldo awal · Rupiah; iuran · Iuran · Rupiah; (5C: material · Penjualan material · Rupiah); masuk_lain · Pemasukan lain · Rupiah; keluar · Pengeluaran · Rupiah; surplus · Surplus/defisit · Rupiah; saldo_akhir · Saldo akhir · Rupiah | arus = Σ; saldo awal = baris pertama; saldo akhir = baris terakhir |
| `pemasukan(Periode)` | Pemasukan per sumber | sumber · Sumber · Teks; jumlah · Transaksi · Angka; nominal · Nominal · Rupiah; porsi · Porsi · Persen | Σ |
| `pengeluaran(Periode)` | Pengeluaran per kategori | kategori · Kategori · Teks; jumlah · Transaksi · Angka; nominal · Nominal · Rupiah; porsi · Porsi · Persen | Σ |
| `rincian(Periode)` | Rincian transaksi kas | tanggal · Tanggal · Tanggal; jenis · Jenis · Teks; kategori · Kategori · Teks; uraian · Uraian · Teks; mitra · Mitra · Teks; masuk · Masuk · Rupiah; keluar · Keluar · Rupiah; bukti · Bukti · Teks ('Ada'/'—') | Σ masuk, Σ keluar |
`catatan` bagian pertama: "Iuran dihitung pada tanggal diterima (iuran hasil impor tanpa tanggal: tanggal 1 bulan iurannya); rinciannya ada di menu Pembayaran Iuran. Saldo '—' = sebelum tanggal mulai pembukuan."
Baris "Jumlah transaksi" untuk Iuran = jumlah pembayaran.

### 6.9 Widget (5B)
```
Widget: KeuanganStats — StatsOverviewWidget + InteractsWithPageFilters — Location: Dasbor (bulan ini) & Analitik › Keuangan
  Heading: fn () => 'Keuangan · '.P->label()
  Stat 'Pemasukan': rupiah(masuk) — Description: 'iuran Rp… · lainnya Rp…' — Icon: Heroicon::OutlinedArrowDownCircle — Color: success
  Stat 'Pengeluaran': rupiah(keluar) — Icon: Heroicon::OutlinedArrowUpCircle — Color: danger
  Stat 'Surplus' / 'Defisit' (label mengikuti tanda): rupiah(abs(masuk − keluar)) — Color: ≥0 success : danger
  Stat 'Saldo kas': saldoAkhir(min(P->sampai, hari ini)) — null → '—' dengan Description 'atur saldo awal di Profil KSM' (kas_mulai > tanggal)
       — Description bila periode memuat hari ini dan diTanganPetugas() > 0: 'termasuk Rp… di tangan petugas' — Icon: Heroicon::OutlinedWallet — Color: primary

Widget: TrenKasChart — ChartWidget line + InteractsWithPageFilters — Location: Dasbor & Analitik › Keuangan — columnSpan full — maxHeight 300px
  Data: 12 bulan berakhir di bulan terakhir periode; datasets: 'Pemasukan' (borderColor #16a34a), 'Pengeluaran' (#dc2626), 'Saldo akhir' (#2563eb, null → celah)
  Sumber: RingkasanKas::perBulan(Periode::duaBelasBulanSampai(P->bulanBulan() terakhir))

Widget: KomposisiPemasukanChart — ChartWidget doughnut + InteractsWithPageFilters — Location: Dasbor & Analitik › Keuangan
  Data: RingkasanKas::pemasukanPerSumber(P) — kosong → dataset kosong + description 'Belum ada pemasukan pada periode ini'

Widget: PengeluaranPerKategoriChart — ChartWidget bar + InteractsWithPageFilters — Location: Analitik › Keuangan
  Options: ['indexAxis' => 'y'] — Data: pengeluaranPerKategori(P)

Widget: TabelArusKas — TabelLaporanWidget (LaporanKeuangan::arusKasBulanan) — Location: Analitik › Keuangan & Laporan › Keuangan
Widget: TabelPemasukan, TabelPengeluaran, TabelRincianKas — TabelLaporanWidget — Location: Laporan › Keuangan
```

### 6.10 Halaman (5B)
```
Page: App\Filament\Ksm\Pages\Analitik\AnalitikKeuangan — extends HalamanAnalitik
  $routePath = $slug = 'analitik/keuangan', $title 'Analitik Keuangan', label 'Keuangan', icon Heroicon::OutlinedBanknotes, sort 10
  getWidgets(): [KeuanganStats, TrenKasChart, KomposisiPemasukanChart, PengeluaranPerKategoriChart, IuranStatsOverview, TabelArusKas]
Page: App\Filament\Ksm\Pages\Laporan\LaporanKeuanganPage — extends HalamanLaporan
  $routePath = $slug = 'laporan/keuangan', $title 'Laporan Keuangan', label 'Keuangan', icon Heroicon::OutlinedDocumentCurrencyDollar, sort 15
  jenisLaporan 'keuangan'; getWidgets(): [TabelArusKas, TabelPemasukan, TabelPengeluaran, TabelRincianKas]
```
Dasbor: sisipkan widget §4.1 (5B). `KelengkapanDataStats` + stat 'Transaksi tanpa bukti'. Master: `RELASI_CATATAN` + `'transaksiKas'`.

### 6.11 Authorization (5B)
| Policy | viewAny | view | create | update | delete | deleteAny |
|---|---|---|---|---|---|---|
| `KategoriTransaksiPolicy` | admin | admin | admin | admin | admin ∧ `! dipakai()` | false |
| `MitraPolicy` | admin | admin | admin | admin | admin ∧ `! dipakai()` | false |
| `TransaksiKasPolicy` | admin | admin | admin | admin | admin | false |
| `AnalitikKeuangan`, `LaporanKeuanganPage` | `canAccess()` admin | | | | | |
Bukti: URL bertanda tangan hanya dibuat di tampilan admin; berlaku 30 menit (sama dengan pola avatar — siapa pun yang memegang tautan dalam 30 menit bisa membukanya).

### 6.12 Tests 5B
`RingkasanKasTest` — fixture (hari ini 2026-11-17; `saldo_awal` 1.000.000, `kas_mulai` 2026-11-01). Pembayaran:
A periode Nov, `dibayar_pada` null, Rp25.000; B periode **Okt**, dibayar **3 Nov**, Rp25.000; C periode Nov (pelanggan lain), dibayar **28 Okt**, Rp50.000;
D periode **Des**, dibayar **10 Nov**, Rp40.000 (dicatat petugas, belum disetor). Transaksi: masuk 5 Nov Rp200.000 (kategori "Dukungan mitra", mitra M);
keluar 6 Nov Rp150.000; keluar 15 Okt Rp70.000. KSM B: keluar 6 Nov Rp999.000.
1. November: iuran **Rp90.000** (A+B+D; per-`periode` akan Rp75.000), masuk Rp290.000, keluar Rp150.000, surplus Rp140.000.
2. `saldoAwal('2026-11-01')` = 1.000.000; `saldoAkhir('2026-11-30')` = **1.140.000**; `saldoAkhir('2026-10-31')` = null; Oktober: iuran Rp50.000, keluar Rp70.000 tetap tampil di arus, saldo "—".
3. `kas_mulai` null → `saldoAkhir('2026-11-30')` = **1.120.000**.
4. `KeuanganStats` November: "termasuk Rp40.000 di tangan petugas"; Desember (tanpa hari ini) tidak menyebutnya.
5. `pemasukanPerSumber` Nov = ['Iuran' => 90.000, 'Dukungan mitra' => 200.000]; KSM B tidak ikut.
6. Laporan Keuangan Excel: sheet "Arus kas bulanan" sel iuran Nov = 90000 (numerik), saldo akhir 1140000.

`BukuKasTest`:
7. Admin membuat pengeluaran: opsi kategori = tepat kategori **keluar aktif** KSM ini (bukan masuk, bukan nonaktif, bukan KSM lain); kirim id kategori masuk / KSM lain → error in-rule; tersimpan `dicatat_oleh_id` = admin, `ksm_id` = tenant.
8. Mengubah transaksi yang kategorinya kini nonaktif tanpa perubahan → tersimpan (nilai tersimpan ada di opsi).
9. Tanggal besok → error; nominal 0 → error, 1 → lolos.
10. Pengeluaran dengan `mitra_id` (simpan model langsung) → `mitra_id` null (hook); pemasukan dengan mitra → tersimpan.
11. Bukti (`Storage::fake('local')`): JPG tersimpan di `bukti-kas/`; ganti → berkas lama hilang; hapus transaksi → berkas hilang; berkas `.svg`/`.exe` → error tipe.
12. Kategori: nama "Iuran" / "PENJUALAN MATERIAL" → error; "Lain-lain" masuk & "Lain-lain" keluar → keduanya boleh; duplikat dalam jenis sama → error;
    kategori terpakai: `jenis` disabled dan tetap setelah simpan dengan state `jenis` dimanipulasi; `DeleteAction` tersembunyi; kategori kosong → terhapus.
13. Petugas & warga: `/transaksi-kas`, `/kategori-transaksis`, `/mitras`, `/analitik/keuangan`, `/laporan/keuangan` → 403.
14. Hapus KSM yang punya kategori, mitra, transaksi (dengan kategori & mitra) → berhasil.

`MitraTest`: 15. `no_hp` '+62 812-3456-7890' → tersimpan '081234567890'; '12345' → error; nama duplikat di KSM sama → error, di KSM lain → boleh; mitra dengan transaksi → tidak bisa dihapus; kolom "Dukungan diterima" = Σ pemasukan bermitra (bukan pengeluaran).

**Sabotase wajib:** `coalesce` → `periode` (→ Rp75.000); batas bawah `kas_mulai` (→ 1.120.000 pada tes 2); `whereBelongsTo` di RingkasanKas (→ KSM B ikut);
filter jenis pada `PilihanMaster::kategori` (→ kategori masuk muncul); hook mitra; penolakan nama sistem; `disabled` jenis kategori terpakai.

---

## 7. Fase 5C — Lingkungan, kemitraan & kinerja

### 7.1 Enum
```
Enum: App\Enums\AktivitasMaterial: string — HasLabel, HasColor, HasIcon
  Pilah = 'pilah': 'Dipilah', 'info', Heroicon::OutlinedFunnel
  Jual = 'jual': 'Dijual', 'success', Heroicon::OutlinedBanknotes
  Olah = 'olah': 'Diolah', 'warning', Heroicon::OutlinedBeaker
  butuhPembeli(): bool → Jual;  butuhHasilOlah(): bool → Olah;  terpulihkan(): bool → Pilah || Olah;  static dari(mixed): ?self (pola JenisTransaksi::dari)

Enum: App\Enums\KelompokMaterial: string — HasLabel, HasColor
  Plastik 'plastik' (#2563eb), Kertas 'kertas' 'Kertas & kardus' (#d97706), Logam 'logam' (#6b7280), Kaca 'kaca' (#0891b2),
  Organik 'organik' (#16a34a), Lainnya 'lainnya' (#a855f7) — warna heks untuk grafik: warnaGrafik(): string
```

### 7.2 Models
```
Model: JenisMaterial   (#[Table('jenis_material')], #[Fillable(['nama', 'kelompok', 'aktif'])])
  Attributes: id; ksm_id foreign cascadeOnDelete; nama string(100) required; kelompok string(20) required (KelompokMaterial);
              aktif boolean default:true; timestamps
  Index: unique(ksm_id, nama)
  Relationships: belongsTo Ksm; hasMany CatatanMaterial
  Methods: dipakai(): bool → catatanMaterial()->exists()

Model: CatatanMaterial   (#[Table('catatan_material')],
        #[Fillable(['tanggal', 'aktivitas', 'jenis_material_id', 'berat_kg', 'mitra_id', 'nilai_jual', 'hasil_olah', 'catatan'])])
  Attributes: id; ksm_id foreign cascadeOnDelete; tanggal date required; aktivitas string(10) required (AktivitasMaterial);
              jenis_material_id foreign(jenis_material.id) nullable nullOnDelete;
              berat_kg decimal(9,1) required; mitra_id foreign(mitra.id) nullable nullOnDelete (pengepul);
              nilai_jual unsignedBigInteger nullable; hasil_olah string(255) nullable; catatan text nullable;
              dicatat_oleh_id foreign(users.id) nullable nullOnDelete; timestamps
  Index: (ksm_id, tanggal), (ksm_id, aktivitas, tanggal)
  Casts: tanggal date, aktivitas AktivitasMaterial, berat_kg 'decimal:1', nilai_jual integer
  Relationships: belongsTo Ksm, JenisMaterial (jenisMaterial), Mitra (pengepul) via mitra_id, User (dicatatOleh)
  Hooks: saving → §3.5 normalisasi
Ksm: relasi jenisMaterial(), catatanMaterial(); RELASI_CATATAN + 'catatanMaterial'. Mitra: catatanMaterial() + dipakai() diperluas.
```

### 7.3 Resources
**`JenisMaterialResource`** — `app/Filament/Ksm/Resources/JenisMaterials/…`, `$tenantRelationshipName = 'jenisMaterial'`, 'Data Master', sort 35, icon `Heroicon::OutlinedArchiveBox`, label 'jenis material'.
Form: `nama` TextInput (required, max:100, `scopedUnique(ignoreRecord: true)`, placeholder 'mis. Plastik PET'); `kelompok` Select (`->options(KelompokMaterial::class)->required()`); `aktif` Toggle default true.
Table: nama searchable, kelompok badge, `catatan_material_count` 'Catatan', aktif IconColumn. Filters: kelompok, TernaryFilter aktif. Actions: ActionGroup [Edit, Delete (§3.7)].

**`CatatanMaterialResource`** — `app/Filament/Ksm/Resources/CatatanMaterials/…`, `$tenantRelationshipName = 'catatanMaterial'`, 'Data Input', sort 50, icon `Heroicon::OutlinedArrowPathRoundedSquare`, label 'catatan material'.
`getEloquentQuery()`: admin → `with(['jenisMaterial', 'pengepul'])`; lainnya `1 = 0`.
Form (columns 2), Imports `Get`:
- `aktivitas` — ToggleButtons — Validation: required, enum — Config: `->options(AktivitasMaterial::class)->inline()->required()->default(AktivitasMaterial::Pilah->value)->live()->columnSpanFull()`.
- `tanggal` — DatePicker — Validation: required, ≤ hari ini — Config: sama dengan transaksi kas.
- `jenis_material_id` — Select — Validation: required, `in` — Config: `->label('Jenis material')->options(fn (?CatatanMaterial $record): array => PilihanMaster::jenisMaterial($record?->jenis_material_id))->required()->searchable()->preload()`.
- `berat_kg` — TextInput — Validation: required, numeric, min:0.1, max:100000 — Config: `->label('Berat')->numeric()->step(0.1)->minValue(0.1)->maxValue(100_000)->suffix('kg')->required()`.
- `mitra_id` — Select — Validation: required bila jual, `in` — Config: `->label('Pengepul')->options(fn (?CatatanMaterial $record): array => PilihanMaster::pengepul($record?->mitra_id))->required(fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhPembeli() ?? false)->visible(fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhPembeli() ?? false)->helperText(fn (): ?string => PilihanMaster::pengepul() === [] ? 'Tambahkan mitra berjenis Pengepul di Data Master → Mitra.' : null)`.
  `PilihanMaster::pengepul()`: mitra KSM ini `jenis = pengepul` dan `aktif`, + tersimpan.
- `nilai_jual` — TextInput — Validation: required bila jual, integer, min:0, max:1000000000 — Config: `->label('Nilai penjualan')->numeric()->integer()->prefix('Rp')->minValue(0)->maxValue(1_000_000_000)->required(fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhPembeli() ?? false)->visible(fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhPembeli() ?? false)`.
- `hasil_olah` — TextInput — Validation: nullable, max:255 — Config: `->placeholder('mis. kompos 12 kg')->maxLength(255)->visible(fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhHasilOlah() ?? false)`.
- `catatan` — Textarea — nullable, max:1000, `->columnSpanFull()`.
Table (`defaultSort('tanggal', 'desc')`): tanggal date; aktivitas badge; `jenisMaterial.nama` (description kelompok label, placeholder 'Tanpa jenis'); `berat_kg` `->numeric(decimalPlaces: 1, locale: 'id')->suffix(' kg')`;
`pengepul.nama` 'Pengepul' placeholder '—'; `nilai_jual` money IDR, `->summarize(Sum::make()->money('IDR', locale: 'id', decimalPlaces: 0))` (`Filament\Tables\Columns\Summarizers\Sum`); `hasil_olah` toggleable hidden.
Filters: aktivitas, jenis_material_id (`PilihanMaster::semuaJenisMaterial()`), mitra_id (pengepul), tanggal (dari/sampai). Actions: ActionGroup [Edit, Delete]; header CreateAction `->using()` dengan `dicatat_oleh_id` (pola §6.6).

### 7.4 Support (5C)
`RingkasanMaterial(Ksm)`: `terpulihkan(dari, sampai): float`, `diolah(...)`, `terjual(...): array{kg: float, nilai: int}`, `perKelompok(...): array<string, float>`,
`perJenis(Periode): list<array{jenis, kelompok, pilah, olah, jual_kg, nilai, stok}>`, `perPengepul(Periode): list<array{mitra, transaksi, kg, nilai}>`, `perBulan(Periode): list<array>`.
`RingkasanKas::penjualanMaterial()` → `RingkasanMaterial::terjual()['nilai']`; `pemasukanPerSumber` + 'Penjualan material'; `perBulan` kolom `material`.
`RingkasanKinerja(Ksm)`: `indikator(Periode): array{penagihan: ?float, cakupan: ?float, pemulihan: ?float, kemandirian: ?float}` (§3.6);
`ringkasan(Periode): list<array{bidang, indikator, nilai, sebelumnya, perubahan, format}>` (laporan kinerja).

### 7.5 Laporan (5C)
| Pembangun / method | Judul | Kolom |
|---|---|---|
| `LaporanLingkungan::perJenis` | Material per jenis | jenis · Teks; kelompok · Teks; pilah · Dipilah · Kg; olah · Diolah · Kg; terpulihkan · Terpulihkan · Kg; jual_kg · Terjual · Kg; nilai · Nilai jual · Rupiah; stok · Stok perkiraan · Kg — total Σ |
| `LaporanLingkungan::perPengepul` | Penjualan per pengepul | mitra · Pengepul · Teks; transaksi · Angka; kg · Kg; nilai · Rupiah — total Σ |
| `LaporanLingkungan::perBulan` | Material per bulan | bulan; terpulihkan · Kg; jual_kg · Kg; nilai · Rupiah; terangkut · Sampah terangkut · Kg; pemulihan · Persen |
| `LaporanKemitraan::kontribusi` | Kontribusi mitra | mitra · Teks; jenis · Teks; dukungan · Dukungan (kas) · Rupiah; jual_kg · Penjualan · Kg; nilai · Nilai penjualan · Rupiah — baris = mitra yang aktif **atau** berkontribusi di periode; total Σ |
| `LaporanKemitraan::daftar` | Daftar mitra | nama; jenis; kontak; no_hp; aktif ('Aktif'/'Nonaktif') |
| `LaporanKinerja::indikator` | Indikator ekonomi sirkular | indikator · Teks; nilai · Periode ini · Persen; sebelumnya · Periode sebelumnya · Persen; perubahan · Perubahan (poin) · Angka |
| `LaporanKinerja::ringkasan` | Ringkasan kinerja | bidang · Teks; indikator · Teks; nilai · Periode ini; sebelumnya; perubahan · Persen — format per baris (Rupiah/Kg/Angka) ditulis sebagai teks berformat di layar; di Excel sel nilai numerik dengan format per baris (`EksporXlsx` menerima format per sel bila baris punya kunci `__format`) |
Ringkasan kinerja berisi: Keuangan (pemasukan, pengeluaran, surplus, saldo akhir), Iuran (terkumpul per periode, penagihan), Operasional (hari angkut, kunjungan, cakupan, sampah terangkut, RT terlayani),
Lingkungan (terpulihkan, terjual kg, nilai jual, diolah), Kemitraan (mitra aktif, dukungan, penjualan ke pengepul).

### 7.6 Widget & halaman (5C)
Widget di `App\Filament\Ksm\Widgets\` (tabel laporan di `…\Widgets\Laporan\`), halaman analitik di `App\Filament\Ksm\Pages\Analitik\`
(extends `HalamanAnalitik`), halaman laporan di `App\Filament\Ksm\Pages\Laporan\` (extends `HalamanLaporan`, `jenisLaporan()` =
`'lingkungan'` / `'kemitraan'` / `'kinerja'`, `pembangun()` = `new Laporan…(Filament::getTenant())`). Notasi `$routePath = $slug = 'x'`
berarti kedua properti statis diisi string yang sama (bukan penugasan berantai di PHP).
```
Widget: LingkunganStats — Stats + InteractsWithPageFilters — Dasbor & Analitik › Lingkungan
  'Material terpulihkan' kg (desc 'dipilah + diolah') · 'Terjual' kg (desc nilai Rp) · 'Diolah' kg · 'Tingkat pemulihan' % (warna §0.2)
Widget: KemitraanStats — Stats + InteractsWithPageFilters — Dasbor & Analitik › Kemitraan
  'Mitra aktif' n (desc per jenis singkat) · 'Dukungan diterima' Rp · 'Penjualan ke pengepul' Rp · 'Mitra berkontribusi' n (dukungan atau penjualan di periode)
Widget: ProgresEkonomiSirkularStats — Stats + InteractsWithPageFilters — Dasbor (bulan ini) & Laporan › Kinerja
  Heading 'Progres ekonomi sirkular' — 4 Stat dari RingkasanKinerja::indikator: nilai persen, description = rumus singkat, color §0.2, null → '—' + 'belum ada data …'
Widget: KomposisiMaterialChart — doughnut — perKelompok(P), warna KelompokMaterial::warnaGrafik()
Widget: MaterialPerBulanChart — bar bertumpuk per kelompok, 12 bulan berakhir di bulan terakhir periode — options ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]]
Widget: CatatanMaterialTerakhirTable — TableWidget — 5 catatan terakhir periode (tanggal, aktivitas badge, jenis, kg, pengepul, nilai) — record url ke CatatanMaterialResource index
Widget: MitraPerJenisChart — doughnut — jumlah mitra aktif per JenisMitra
Widget: TabelMaterialPerJenis, TabelPenjualanPengepul, TabelMaterialPerBulan, TabelKontribusiMitra, TabelDaftarMitra, TabelIndikator, TabelRingkasanKinerja — TabelLaporanWidget

Page: AnalitikLingkungan — $routePath = $slug = 'analitik/lingkungan', label 'Lingkungan', icon Heroicon::OutlinedGlobeAsiaAustralia, sort 30
  widgets: [LingkunganStats, KomposisiMaterialChart, MaterialPerBulanChart, TabelMaterialPerJenis, CatatanMaterialTerakhirTable]
Page: AnalitikKemitraan — $routePath = $slug = 'analitik/kemitraan', label 'Kemitraan', icon Heroicon::OutlinedHandRaised, sort 40
  widgets: [KemitraanStats, MitraPerJenisChart, TabelKontribusiMitra]
Page: LaporanLingkunganPage — $routePath = $slug = 'laporan/lingkungan', sort 30 — [TabelMaterialPerJenis, TabelPenjualanPengepul, TabelMaterialPerBulan]
Page: LaporanKemitraanPage — $routePath = $slug = 'laporan/kemitraan', sort 40 — [TabelKontribusiMitra, TabelDaftarMitra]
Page: LaporanKinerjaPage — $routePath = $slug = 'laporan/kinerja', $title 'Laporan Kinerja Terintegrasi', label 'Kinerja', icon Heroicon::OutlinedPresentationChartLine, sort 50
  — [ProgresEkonomiSirkularStats, TabelIndikator, TabelRingkasanKinerja]
```
Dasbor: sisipkan widget §4.1 (5C).

### 7.7 Authorization (5C)
| Policy | viewAny | view | create | update | delete | deleteAny |
|---|---|---|---|---|---|---|
| `JenisMaterialPolicy` | admin | admin | admin | admin | admin ∧ `! dipakai()` | false |
| `CatatanMaterialPolicy` | admin | admin | admin | admin | admin | false |
| `MitraPolicy::delete` (ubah) | | | | | admin ∧ tidak dipakai kas **maupun** material | |
| Halaman analitik/laporan 5C | `canAccess()` admin | | | | | |

### 7.8 Tests 5C
`RingkasanMaterialTest` — fixture (hari ini 2026-11-17): Nov pilah Plastik PET (Plastik) 100,0 (2 Nov); pilah Kardus (Kertas) 40,0 (4 Nov); olah Organik 30,0 hasil "kompos 12 kg" (8 Nov);
jual Plastik PET 60,0 Rp180.000 ke pengepul P (12 Nov); Okt pilah Plastik PET 20,0; KSM B pilah 999,0; rincian pengangkutan Nov total 340,0 kg.
1. Terpulihkan Nov = **170,0** (bukan 230 dengan jual, bukan 190 dengan Oktober, bukan 1.169 dengan KSM B); komposisi Plastik 100 / Kertas 40 / Organik 30.
2. Terjual 60,0 kg / Rp180.000; stok Plastik PET s.d. 30 Nov = **60,0** (120 − 60).
3. Pemasukan kas Nov + 'Penjualan material' Rp180.000; arus kas bulanan kolom material.
4. Tingkat pemulihan = 170/340 = **50 %**.

`KinerjaTest` (gabungan fixture keuangan §6.12 + material di atas):
5. Kemandirian = (90.000 + 180.000) / 150.000 = **180 %** (memasukkan dukungan mitra Rp200.000 akan memberi 313 %).
6. Penagihan Nov = Σ iuran per `periode` Nov ÷ target aktif; periode Desember (depan) → "—".
7. Semua penyebut 0 (KSM baru) → keempat indikator "—", tanpa division by zero.
8. Laporan Kinerja: kolom "periode sebelumnya" memakai Oktober; Excel sel nilai numerik.

`CatatanMaterialTest`:
9. Jual tanpa pengepul/nilai → error; pilah dengan `mitra_id`/`nilai_jual` (simpan model) → keduanya null; olah → `hasil_olah` tersimpan, pilah → null.
10. Opsi pengepul = mitra **Pengepul aktif** KSM ini (bukan Swasta, bukan nonaktif, bukan KSM lain) + tersimpan; id mitra Swasta → error in-rule.
11. Berat 0 → error; 0,1 → lolos; 100.001 → error. Petugas & warga → 403 di catatan material, jenis material, analitik & laporan lingkungan/kemitraan/kinerja.
12. Mitra yang hanya dipakai sebagai pengepul → tidak bisa dihapus; jenis material terpakai → tidak bisa dihapus.
13. Master: KSM yang hanya punya catatan material kemarin → "Aktif mencatat".

**Sabotase wajib:** aktivitas jual ikut terpulihkan; batas periode material; tenant di RingkasanMaterial; filter `jenis = pengepul`; dukungan mitra di kemandirian; hook normalisasi.

---

## 8. Konsistensi lintas tahap (diperiksa)

| Jalur | Aturan | Tahap | Pemeriksaan |
|---|---|---|---|
| Filter → widget → Excel/cetak | §3.1, §3.8 | URL/sesi → `Periode::dariFilter` → pembangun → layar; aksi Excel memanggil pembangun yang sama dengan `$this->periode()`; cetak = halaman yang sama | Tes 13/14 (5A), 6 (5B): angka layar = sel Excel; filter rusak → default di layar **dan** di Excel |
| Iuran → kas | §3.2 | Terima iuran (petugas) / admin → baris `pembayaran_iuran` → `RingkasanKas` per tanggal diterima; Rekap Iuran per periode | Fixture B/C/D membedakan dua basis; setoran tidak mengubah kas (lunas langsung) |
| Kategori → transaksi → laporan | §3.7, §6.4 | jenis transaksi dipilih → opsi kategori jenis sama → simpan → laporan mengelompokkan per kategori | Kategori terpakai: jenis terkunci; simpan ulang transaksi dengan kategori nonaktif tetap sah |
| Penjualan material → kas | §3.5 | catatan jual → `RingkasanKas::penjualanMaterial` | Mengubah nilai jual langsung mengubah kas (tanpa salinan) |
| Hapus master | §3.7 | kebijakan menolak bila dipakai; FK `nullOnDelete` hanya untuk cascade hapus KSM | Tes hapus KSM penuh berhasil; tes hapus master terpakai ditolak |
| Otorisasi | §5.9, §6.11, §7.7 | `canAccess` halaman, `canView` widget (mount **dan** update), kebijakan resource, `authorize` aksi Excel | Petugas/warga 403 di URL halaman **dan** `Livewire::test(widget)`; data kustom difilter tenant di sumbernya |

---

## 9. Setelah tiap fase
1. Setelah deploy **5A**: di produksi cek `select count(*) from pelanggan where (rt is not null and rt <> '') or (rw is not null and rw <> '')`. 0 = tidak perlu apa-apa (kolom lama boleh dihapus di fase berikut); > 0 = buat RT di Data Master lalu tetapkan RT pelanggan itu (nilainya tetap tersimpan di kolom lama).
2. `vendor/bin/pint --dirty --format agent`; suite penuh `php artisan test --compact`; ulang dengan `route:cache` (`APP_ROUTES_CACHE=<tmp> APP_DOMAIN=ksmdesa.test php artisan route:cache`) — nama rute `analitik.*`/`laporan.*` baru.
3. Verifikasi di Browser pane: dasbor admin, satu halaman analitik, satu laporan (unduh Excel, buka di Numbers/LibreOffice bila ada — atau baca ulang dengan OpenSpout), pratinjau cetak (§4.7), panel master; screenshot.
4. Audit `filament-security-audit` (fokus: data kustom ber-tenant, filter halaman tak tervalidasi, unggahan bukti, URL bertanda tangan); perbaiki temuan.
5. Commit → push → deploy (`sudo -u ksmdesa -i bash -c '$HOME/deploy/deploy.sh'`) → cek produksi → perbarui `docs/README.md` (model data, aturan kanonik §3, riwayat fase) → Qdrant.
