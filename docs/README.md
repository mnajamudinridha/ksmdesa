# Pengetahuan proyek KSM Desa

Dokumen hidup: arsitektur, aturan bisnis, konvensi, dan jebakan yang sudah ditemukan. Baca ini sebelum
mengubah kode. Rencana per fase (lengkap dengan spesifikasi) ada di [`plans/`](plans/).

> **Repo ini publik.** Jangan pernah commit data warga (nama, nomor HP, buku iuran, CSV impor di
> `storage/app/private/imports/`), `.env`, `auth.json`, kredensial server, atau kunci SSH. Detail server
> dan akses produksi disimpan di catatan privat pemilik, bukan di repo.

## 1. Produk & istilah

KSM (Kelompok Swadaya Masyarakat) mengelola pengangkutan sampah desa yang dibayar iuran bulanan warga.

| Istilah | Arti |
|---|---|
| **KSM** | Satu per desa; *tenant*. Model `Ksm` (tabel `ksm`). |
| **Pelanggan** | Rumah/usaha yang sampahnya diangkut dan membayar iuran. Satu orang bisa punya beberapa (rumah + warung). |
| **Akun warga** | Akun login (`User` peran `warga`) milik pelanggan. **Selalu lahir dari Pelanggan** (otomatis), tidak pernah dari menu Pengguna. |
| **Petugas** | Petugas pengangkut; ditugaskan ke pelanggan (`pelanggan.petugas_id`). |
| **Admin** | Pengurus KSM. Administrator pusat (`is_super_admin`) bertindak sebagai admin di semua KSM. |
| **Tarif layanan** | Golongan iuran (mis. warga biasa 25 rb, catering kecil 50 rb, perusahaan/sekolah 100 rb). |
| **Pembayaran iuran** | Satu bulan iuran satu pelanggan (`periode` = tanggal 1 bulan itu). |
| **Pengangkutan** | Satu ronde petugas per hari, dengan rincian per rumah (status + perkiraan kg). |
| **Setoran** | Uang iuran yang diserahkan petugas ke pengurus (Fase 4). |

## 2. Arsitektur

- **Stack:** Laravel 13, Filament 5, Livewire 4, PHP 8.4, PHPUnit 12 (bukan Pest), Laravel Boost, Filament Blueprint.
- **Dua panel Filament:**
  - `admin` (master) — terikat ke domain apex `APP_DOMAIN` (`->domain()`), path `/admin`. Mengelola KSM,
    administrator pusat, dan admin/petugas tiap KSM.
  - `ksm` (desa) — `->path('')`, tenant `Ksm` lewat `->tenantDomain('{tenant:slug}.'.APP_DOMAIN)`.
- **Satu database**, isolasi via `ksm_id` + `$tenantRelationshipName` di setiap resource. Filament juga memasang
  global scope tenant dan mengisi `ksm_id` saat *creating*.
- **Host → KSM:** `App\Support\KsmDariHost` (di-memo dengan `once()`); `EnsureKsmSubdomain` membalas 404 di
  apex/subdomain tak dikenal (Filament mendaftarkan rute auth panel `ksm` tanpa domain).
- **Login desa** pakai **nomor HP** (`App\Filament\Ksm\Pages\Auth\Login`), dicari dalam KSM dari host; administrator
  pusat login dengan email. Sesi per host.
- **Default tenant** (`User::getDefaultTenant`): administrator pusat tetap di subdomain tempat ia login.
- **Waktu:** timestamp disimpan UTC; "hari ini" = hari kalender desa (`APP_ZONA_WAKTU`, default `Asia/Makassar`/WITA).
- **Antrean:** scheduler menjalankan `queue:work --stop-when-empty` tiap menit (hosting tanpa supervisor).

## 3. Model data (ringkas)

```
ksm ─┬─ users (ksm_id, peran, no_hp unik per KSM, pakai_sandi_default, aktif, avatar)
     ├─ tarif_layanan
     ├─ wilayah (rt, rw?, dusun, jumlah_kk)  unik (ksm, rw, rt) — RW kosong dijaga validasi form
     ├─ pelanggan (tarif, wilayah_id→wilayah, petugas_id→users, user_id→akun warga, alamat/patokan/koordinat;
     │             rt/rw teks lama tidak dipakai lagi — dibiarkan agar migrasi aditif)
     │    └─ pembayaran_iuran (periode, nominal, dibayar_pada, petugas_id, setoran_id)  unik (pelanggan, periode)
     ├─ pengangkutan (petugas_id, tanggal)  unik (petugas, tanggal)
     │    └─ pengangkutan_rincian (pelanggan_id, status, perkiraan_kg)  unik (pengangkutan, pelanggan)
     ├─ setoran (petugas_id, diterima_oleh_id, tanggal)
     ├─ kategori_transaksi (jenis masuk|keluar, nama, aktif)  unik (ksm, jenis, nama)
     ├─ mitra (nama, jenis, kontak, no_hp, aktif)  unik (ksm, nama)
     ├─ transaksi_kas (tanggal, jenis, kategori_transaksi_id?, mitra_id? (hanya masuk), nominal, uraian, bukti?, dicatat_oleh_id)
     ├─ jenis_material (nama, kelompok plastik|kertas|logam|kaca|organik|lainnya, aktif)  unik (ksm, nama)
     └─ catatan_material (tanggal, aktivitas pilah|jual|olah, jenis_material_id?, berat_kg, mitra_id? + nilai_jual? (hanya jual), hasil_olah? (hanya olah), dicatat_oleh_id)
ksm: logo, latar_login (json, ≤3), sandi_default_hash (#[Hidden]), saldo_awal, kas_mulai (buku kas)
pengaturan_master (satu baris): logo, latar_login — tampilan panel master
```

Pengguna tidak pernah dihapus (dinonaktifkan). FK ke `users` dari tabel yang ikut terhapus bersama KSM memakai
`cascadeOnDelete` (bukan restrict) karena menghapus KSM juga meng-cascade penggunanya.

## 4. Aturan kanonik (satu sumber, dipakai di mana-mana)

| Aturan | Tempat | Inti |
|---|---|---|
| Hari desa | `App\Support\Hari` | `Hari::ini()` / `Hari::tanggal($mundur)`; `Hari::sebut()` = "hari ini" / "kemarin" / "Senin, 16 November". Bandingkan kolom `date` sebagai **string `Y-m-d`**, jangan sebagai instant (beda 8 jam). |
| Nomor HP | `App\Support\NomorHp` | Normalisasi `+62/62/8…` → `08…`; valid `^08\d{8,11}$`. Unik dicek pada nilai **ternormalisasi**. |
| Akun warga | `App\Support\AkunWarga` | Satu-satunya pembuat/penaut akun warga. Nomor yang sudah dipakai di KSM = tautkan ke pemiliknya. |
| Sandi default desa | `Ksm::cocokSandiDefault`, hook `User::saving`, `Ksm::updated` | Hanya untuk warga. Admin/petugas tidak boleh memakainya. Naik peran dari warga **selalu** butuh sandi baru (hash tidak bisa dibandingkan dengan hash); di luar form → sandi acak (fail closed). |
| Status iuran | `App\Enums\StatusIuran::tentukan()` | Lunas/Kurang/Lebih/Belum/Nonaktif dari nominal tercatat. *Kebijakan sementara — keputusan pemilik masih TODO.* |
| Bulan yang boleh dicatat petugas | `App\Support\BulanIuran::pilihan()` | 6 bulan ke belakang s.d. 2 ke depan, hanya yang belum tercatat; dipakai sebagai opsi CheckboxList (rule `in`). |
| Status setor | `PembayaranIuran::statusSetor()`, `scopeBelumDisetor` | `petugas_id` null = langsung ke pengurus; terisi & `setoran_id` null = **belum disetor**; keduanya terisi = disetor. Lunas **tidak** bergantung pada setoran. |
| Jendela koreksi petugas | `Pengangkutan::bisaDiubahPetugas`, `PembayaranIuranPolicy` | Pengangkutan: sampai akhir hari berikutnya. Pembayaran: sampai masuk setoran. |
| Periode laporan | `App\Support\Periode::dariFilter()` | Tahun + bulan / "semua" (setahun), hari desa. Filter halaman **tidak divalidasi Filament** dan bisa diubah lewat URL → nilai aneh jatuh ke bulan berjalan, tidak pernah melempar. |
| Tanggal kas iuran | `PembayaranIuran::scopeDiterimaAntara` | `COALESCE(dibayar_pada, periode)` — iuran impor tanpa tanggal masuk pada tanggal 1 bulan iurannya. Rekap Iuran & dasbor iuran tetap per `periode`. |
| Cakupan per RT | `RingkasanPengangkutan::perWilayah()` | Baris tiap RT + "Tanpa RT" + "Pelanggan nonaktif"; cakupan = pelanggan **aktif saat ini** yang diangkut ≥1× ÷ aktif. Σ kg semua baris = `berat()` periode. |
| Tabel laporan | `App\Support\Laporan\TabelLaporan` + `Laporan…` | Satu objek untuk layar (widget `TabelLaporanWidget`), Excel (`EksporXlsx`), dan cetak — angka tidak bisa berbeda. |
| KSM aktif mencatat | `Ksm::scopeAktifMencatat`, `Ksm::HARI_AKTIF_MENCATAT` | Ada catatan baru (`created_at`) di `Ksm::RELASI_CATATAN` (pengangkutan, iuran, setoran, transaksi kas) dalam 7 hari. Dipakai stat, badge, dan filter panel master. |
| Kas & saldo | `App\Support\RingkasanKas` | Masuk = iuran (tanggal kas) + transaksi masuk (+ penjualan material 5C); keluar = transaksi keluar. Saldo hari D = `saldo_awal` + masuk − keluar dari `kas_mulai` s.d. D; sebelum `kas_mulai` → null ("—"). Arus selalu dilaporkan. Uang di tangan petugas termasuk saldo. |
| Material | `App\Support\RingkasanMaterial`, hook `CatatanMaterial::saving` | Terpulihkan = dipilah + diolah (jual tidak ditambahkan lagi); stok per jenis = dipilah − terjual s.d. akhir periode. Nilai jual = pemasukan kas pada tanggal catatan, **dibaca langsung** oleh `RingkasanKas` — tidak pernah disalin ke `transaksi_kas`, jadi ubah/hapus/ganti aktivitas langsung mengubah kas. Pembeli & nilai hanya untuk jual, hasil olah hanya untuk olah. Pengepul = mitra aktif berjenis Pengepul. |
| Kemitraan | `App\Support\RingkasanMitra` | Dukungan = transaksi kas **masuk** yang mencantumkan mitra; penjualan = catatan jual ke mitra itu. Laporan memuat mitra aktif + yang berkontribusi di periode. |
| Indikator ekonomi sirkular | `App\Support\RingkasanKinerja::indikator()` | Penagihan = iuran per bulan iuran ÷ (target × bulan dari bulan iuran pertama s.d. bulan ini); cakupan = §cakupan per RT; pemulihan = terpulihkan ÷ sampah terangkut; kemandirian = (iuran kas + penjualan) ÷ pengeluaran. Penyebut 0 → null ("—"). |
| Kategori & mitra transaksi | `PilihanMaster::kategori()/mitra()`, hook `TransaksiKas::saving` | Kategori hanya dari jenis yang sama (aktif + tersimpan); kategori terpakai: jenis terkunci, tidak bisa dihapus. Nama "Iuran"/"Penjualan material" ditolak (dihitung otomatis). Mitra hanya untuk pemasukan (hook mengosongkan pada pengeluaran). |

## 5. Peran & akses

| | Admin desa / pusat | Petugas | Warga |
|---|---|---|---|
| Pengguna (admin & petugas saja) | kelola | – | – |
| Pelanggan (+ akun warga: foto, HP, email, sandi, bisa login) | kelola | lihat pelanggan tugasnya, tanpa uang | – |
| Pembayaran iuran | semua; hapus hanya yang belum disetor | "Iuran diterima": miliknya, ubah/hapus sampai disetor | riwayat sendiri di dasbor |
| Terima iuran (widget Tugas hari ini) | – | rumah tugasnya yang aktif | – |
| Pengangkutan | semua | miliknya; ubah sampai akhir hari berikutnya | riwayat rumahnya di dasbor |
| Setoran petugas | kelola, batalkan | – | – |
| Profil KSM (nama, logo, latar login, sandi default, saldo awal & mulai pembukuan) | ya | – | – |
| Buku Kas, Catatan Material, Kategori Transaksi, Jenis Material, Mitra, Analitik & Laporan | kelola / lihat (data master terpakai: nonaktifkan, tidak bisa dihapus) | – | – |

Warga hanya melihat dasbornya (status iuran bulan ini, kontak petugas, riwayat iuran & pengangkutan).

## 6. Konvensi kode

- **Nama domain berbahasa Indonesia** (model, kolom, method: `pelanggan`, `nomor_urut`, `bisaDiubahPetugas`); komentar/PHPDoc bahasa Inggris.
  Tabel bentuk tunggal Indonesia + `#[Table('...')]` (generator membuat jamak — ganti).
- Atribut model: `#[Fillable]`, `#[Hidden]`, `#[Table]`. Kolom yang tidak boleh dari input (`ksm_id`, `user_id`,
  `petugas_id` pembayaran, `setoran_id`, `is_super_admin`) **tidak fillable** — isi lewat relasi/`forceFill`.
- Resource **satu file** + folder `Pages/` (bukan `Schemas/`/`Tables/` hasil generator). Aksi baris di
  **`ActionGroup`** (menu titik tiga). Halaman pengaturan dibagi **`Section`**.
- **Dasbor & laporan:** dasbor desa = `App\Filament\Ksm\Pages\Dashboard` dengan **daftar widget eksplisit** (bawaan
  memuat semua widget panel). Halaman analitik/laporan = turunan `Filament\Pages\Dashboard` (`HalamanAnalitik` /
  `HalamanLaporan`, trait `PunyaFilterPeriode`), `$routePath` dan `$slug` sama (`'laporan/operasional'`). Bagian laporan =
  pembangun di `App\Support\Laporan\` + widget turunan `TabelLaporanWidget` (data kustom `records()`, tidak lazy).
  Excel = `EksporXlsx::unduh()` (OpenSpout, `StreamedResponse` dari aksi Livewire); cetak = `window.print()` + aturan
  `@media print` di `resources/views/filament/laporan/kop.blade.php`.
- **Grid dasbor** (master & desa, semua peran): grid 2 kolom, **tidak boleh ada baris setengah kosong**. Widget setengah
  lebar selalu berpasangan untuk setiap peran yang melihatnya ("Selamat datang" + widget pendamping `SorotanMaster` /
  `SorotanKsm`; "Komposisi pemasukan" + "Komposisi pengeluaran"; dua tabel riwayat warga); sisanya `'full'`. Grup kartu
  stat mengisi baris penuh: 3, 4, atau 6 kartu (bawaan Filament: 3 kolom, atau 4 bila jumlah ≡ 1 mod 3); jumlah yang
  berubah-ubah mengatur `getColumns()` sendiri (`WargaStatsOverview`). Dijaga `tests/Feature/GridDasborTest.php` —
  widget dasbor baru otomatis ikut diperiksa.
- Query resource per peran di `getEloquentQuery()` (admin semua / petugas miliknya / lainnya `whereRaw('1 = 0')`) **plus** policy.
- Select/CheckboxList: opsi = himpunan yang sah → rule `in` bawaan Filament menolak nilai lain di server.
  Sertakan nilai tersimpan di opsi (mis. `PilihanPengguna::petugas($tersimpan)`) agar simpan tanpa perubahan tidak gagal.
- Tampilan panel (logo + nama, logo besar & latar di login) satu tempat: `App\Filament\Shared\TampilanPanel`; data logo/latar
  lewat trait `App\Models\Concerns\PunyaTampilanLogin` (dipakai `Ksm` dan `PengaturanMaster`; halaman master **Tampilan**).
- Berkas: avatar (URL bertanda tangan) dan bukti kas (`bukti-kas/{id KSM}`, diunduh lewat aksi) di disk `local` (privat); logo & latar login di disk `public` (URL **relatif** `/storage/…`, jangan diubah ke absolut);
  `FileUpload` global `->preventFilePathTampering()`; model menghapus berkas lama di hook `updated`/`deleted`.
  Avatar inisial digambar lokal (`InisialAvatar`) — jangan pakai ui-avatars (membocorkan nama).
- Setelah mengubah PHP: `vendor/bin/pint --dirty --format agent`.

## 7. Pengujian

- PHPUnit + `Livewire::test`; satu panel per test method (state panel bocor antar-panel).
- Fixture KSM lain: helper `diKsmLain()` (setel tenant null) — observer tenant Filament memindahkan record baru ke tenant aktif.
- Waktu: `travelTo(CarbonImmutable::parse('… 08:00', 'Asia/Makassar'))`.
- Repeater: `Repeater::fake()`. FileUpload: `fillForm` **menambah** — kosongkan dulu dengan `[]`.
  Field di modal aksi: `assertFormFieldHidden('x', 'mountedActionSchema0')`.
- Beberapa `actingAs` dalam satu test: `flushSession()` di antaranya (AuthenticateSession menyimpan hash sandi pengguna pertama).
- `Storage::fake('local')`: `temporaryUrl` tidak bertanda tangan → uji privasi foto di disk asli.
- **Uji sabotase wajib:** matikan aturan, tes terkait harus merah. Lapisan ganda diuji per lapisan.
- `route:cache` diuji dengan berkas cache terpisah: `APP_ROUTES_CACHE=<tmp> APP_DOMAIN=ksmdesa.test php artisan route:cache`.

## 8. Jebakan yang sudah ditemukan

| Gejala | Penyebab | Solusi |
|---|---|---|
| Aturan "satu per hari" lolos, lalu error UNIQUE | DatePicker menyerahkan `Y-m-d H:i:s` ke rule | Bandingkan `Carbon::parse($value)->toDateString()` |
| Foto tidak muncul di field virtual | `formatStateUsing` menimpa `afterStateHydrated` (slot tunggal) dan melewatkan `hydrateFiles()` | `afterStateHydrated(fn ($c) => [$c->state(...), $c->hydrateFiles()])` |
| `defaultItems()` menghapus prefill Repeater | menulis slot default yang sama dengan `default()` | pakai `default()` saja |
| Field form diisi dari akun A ikut tersimpan ke akun B | nomor HP menautkan pelanggan ke akun lain | tulis balik hanya ke akun yang ditampilkan (atau akun baru saat create) |
| 500 di Profil saya administrator pusat | layout penuh butuh tenant | `->profile(..., isSimple: true)` |
| Slug profil bentrok | dua halaman profil default `profile` | `profil-saya` / `profil-ksm` |
| Livewire test kehilangan host | header Host tidak terbawa ke request berikutnya | `URL::forceRootUrl('http://slug.ksmdesa.test')` |
| `make:filament-widget --stats-overview/--chart/--table` | menghasilkan Widget biasa + view dengan sufiks ganda | tulis widget manual |
| Gambar latar bisa menyuntik CSS | path masuk ke `url()` | hanya path `latar-login/[A-Za-z0-9._-]+` (trait `PunyaTampilanLogin`) |
| Simpan ulang menolak berkas yang sudah tersimpan ("path berkas yang tidak diizinkan") | `preventFilePathTampering()` mencocokkan path dengan model form | form halaman kustom wajib `->model($record)` |
| Pratinjau unggahan macet "Memuat / Menunggu ukuran berkas" di subdomain (di master normal) | URL disk `public` absolut ke apex → FilePond mengambil berkas lintas-origin, server tanpa header CORS | `filesystems.disks.public.url = '/storage'` (relatif host; semua subdomain melayani `public/storage` yang sama) |
| SelectFilter `->relationship()` | opsi dimuat dari seluruh tabel relasi (bergantung pada scope tenant implisit) | opsi eksplisit `whereBelongsTo(Filament::getTenant())` |
| Uji sabotase "hapus `whereBelongsTo`" tetap hijau | scope tenant Filament ikut menyaring selama panel `ksm` aktif | uji lapisan eksplisit dengan `Filament::setCurrentPanel('admin')` (scope hanya berjalan di panelnya) |
| Data tabel kustom (`records()`) bocor antar-KSM | array tidak kena scope global maupun kebijakan | pembangun memfilter `whereBelongsTo($ksm)` sendiri |
| Cetak laporan kosong / teks tak terbaca | widget lazy belum dimuat; mode gelap mencetak teks terang tanpa latar | `TabelLaporanWidget::$isLazy = false`; `@media print` paksa warna gelap di latar putih, A4 lanskap |
| `assertSeeInOrder(['Label', '2'])` pada kartu Stat lolos padahal salah | "2" ada di mana saja di HTML | baca nilai kartu dengan trait `Tests\Concerns\BacaNilaiStat` |
| Widget dasbor tak muncul di respons HTTP tes | widget Filament lazy (placeholder) | `assertSeeLivewire(Widget::class)` |
| Filter tabel kustom: `->schema([...])` deprecated | Filament 5 `Filter` memakai `->form([...])` | pakai `->form()` |
| Isian wajib Profil KSM kosong di tes (`saldo_awal`) | instans dari factory tidak membaca default kolom DB | default juga di model (`Ksm::$attributes`) |
| Uji `scopedUnique` dengan `assertHasFormErrors(['x' => 'unique'])` gagal | `scopedUnique` aturan closure (bukan aturan bernama) | beri `->validationMessages(['unique' => '…'])` dan uji teks pesannya |
| `callAction` kedua di komponen yang sama: "Action [create] was not found" | aksi yang gagal validasi tetap ter-mount | panggil di `Livewire::test()` baru |
| Verifikasi browser: widget dasbor tetap "Memuat..." | widget lazy dimuat saat terlihat; pane browser tersembunyi tidak memicunya | tampilkan pane, atau periksa halaman laporan (tabel tidak lazy) / tes Livewire |
| Uji teks bantuan field (`helperText`) | Filament 5 menjadikannya skema anak — tidak ada `getHelperText()` | taruh keputusannya di method statis dan uji method itu (mis. `TransaksiKasResource::peringatanPengepul`) |
| Isi modal aksi tidak ada di `->html()` tes | konten modal tidak dirender di HTML Livewire test | uji state (`mountedActions.0.data`) / `assertFormFieldExists(..., 'mountedActionSchema0', …)` |
| Widget Stat/Chart memuat ulang tiap 5 detik | bawaan `CanPoll` `'5s'` | widget laporan/keuangan: `protected ?string $pollingInterval = null` |
| Dua widget sebaris tidak sama tinggi | grid meregangkan pembungkus widget, bukan `fi-section` di dalamnya | `sorotan-hari-ini.blade.php` meregangkan section kartu akun (tanpa tema kustom hanya kelas CSS Filament yang ada — pakai kelas `fi-account-widget-*`, bukan utilitas Tailwind baru) |
| Bukti PDF tidak terbuka di Chrome | Laravel menyajikan berkas disk `local` dengan CSP `sandbox`; penampil PDF tidak berjalan di dokumen ber-sandbox | unduh lewat aksi (`Storage::download`, ditangkap Livewire) dan `FileUpload::downloadable()`, bukan `openable()` |
| Berkas bukti tertinggal saat KSM dihapus | cascade DB menghapus baris tanpa event model | bukti per KSM (`bukti-kas/{id}`), `Ksm::deleted` menghapus foldernya (avatar masih punya celah yang sama) |
| Nama `=HYPERLINK(…)` jadi rumus aktif di Excel | OpenSpout `Cell::fromValue()` membuat sel rumus untuk teks berawalan `=`; petugas bisa mengganti namanya sendiri | semua teks ditulis sebagai `StringCell` (`EksporXlsx::sel`); tes memeriksa XML lembar tanpa `<f>` (pembaca OpenSpout mengembalikan teks rumus apa adanya) |

## 9. Deploy & operasi

- Deploy manual dari `main` dengan `deploy/deploy.sh` (salinan terpasang di server, dijalankan sebagai user aplikasi):
  backup DB → `down` → `reset --hard` → composer `--no-dev` → `npm ci && build` → `migrate --force` → `optimize` +
  `filament:optimize` → `up` → health check (apex `/up` `/` `/admin/login` 200, `/admin` 302; subdomain uji `/up` 200,
  `/admin/login` 404; `/login` di apex 404). Gagal = situs tetap maintenance + pesan rollback.
- Pesan `public/storage link already exists` saat deploy = normal.
- Admin pusat pertama: `php artisan app:super-admin`. Impor buku iuran: `php artisan app:import-iuran {ksm} {file}`.
  Akun warga untuk pelanggan lama: `php artisan app:akun-warga {ksm}` (idempoten).
- Wildcard subdomain: DNS `*` + alias server + sertifikat wildcard (detail di catatan server privat).

## 10. Riwayat fase & keputusan

| Fase | Isi | Rencana |
|---|---|---|
| 1 | Pengguna per KSM, peran, login HP, foto, logo | [plans/…pengangkutan.md §3](plans/2026-11-17-peran-pengguna-dan-pengangkutan.md) |
| 2 | Detail pelanggan, penugasan petugas, akun warga otomatis, sandi default desa | §4 dokumen yang sama |
| 3 | Pengangkutan harian per rumah, dasbor per peran | §5 dokumen yang sama |
| — | Warga hanya dari Pelanggan; Pengguna = admin & petugas; ActionGroup; Section; branding (logo + nama, latar login) | catatan di dokumen yang sama |
| 4 | Petugas catat iuran, setoran ke pengurus; logo & latar login panel master | [plans/2026-11-17-fase-4-petugas-catat-iuran.md](plans/2026-11-17-fase-4-petugas-catat-iuran.md) |
| 5A | Master RT/RW, dasbor eksplisit + Kelengkapan data, Analitik & Laporan Operasional (Excel, cetak), Excel Rekap Iuran, widget panel master | [plans/2026-11-17-fase-5-dasbor-dan-laporan.md](plans/2026-11-17-fase-5-dasbor-dan-laporan.md) §5 |
| 5B | Buku Kas (bukti privat), Kategori Transaksi, Mitra, saldo awal & mulai pembukuan, Analitik & Laporan Keuangan, kartu keuangan di dasbor | dokumen yang sama §6 |
| 5C | Jenis & Catatan Material (penjualan → kas), Analitik Lingkungan & Kemitraan, Laporan Lingkungan / Kemitraan / Kinerja terintegrasi, kartu progres ekonomi sirkular, lingkungan & kemitraan di dasbor | dokumen yang sama §7 |
| — | Grid dasbor tanpa slot kosong: widget pendamping "Selamat datang" (master: KSM belum mencatat 7 hari; admin: petugas belum mencatat hari ini; petugas: ronde terakhir; warga: terakhir diangkut), kartu master ke-6 "Material terpulihkan 30 hari", donat "Komposisi pengeluaran", kartu warga selebar baris | permintaan pemilik 2026-11-17 (tanpa dokumen rencana) |

**Menunggu keputusan pemilik:** kebijakan `StatusIuran::tentukan()` (kurang/lebih/nonaktif), setelan email (`MAIL_MAILER=log`).
**Fase 5 selesai** (5A–5C): [plans/2026-11-17-fase-5-dasbor-dan-laporan.md](plans/2026-11-17-fase-5-dasbor-dan-laporan.md).
Kandidat perbaikan kecil: widget analitik 5A (`OperasionalPeriodeStats`, `BeratTerangkutChart`, widget master) masih polling 5 detik.
