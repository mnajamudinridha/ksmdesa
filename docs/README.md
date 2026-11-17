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
     ├─ pelanggan (tarif, petugas_id→users, user_id→akun warga, alamat/RT/RW/patokan/koordinat)
     │    └─ pembayaran_iuran (periode, nominal, dibayar_pada, petugas_id, setoran_id)  unik (pelanggan, periode)
     ├─ pengangkutan (petugas_id, tanggal)  unik (petugas, tanggal)
     │    └─ pengangkutan_rincian (pelanggan_id, status, perkiraan_kg)  unik (pengangkutan, pelanggan)
     └─ setoran (petugas_id, diterima_oleh_id, tanggal)
ksm: logo, latar_login (json, ≤3), sandi_default_hash (#[Hidden])
pengaturan_master (satu baris): logo, latar_login — tampilan panel master
```

Pengguna tidak pernah dihapus (dinonaktifkan). FK ke `users` dari tabel yang ikut terhapus bersama KSM memakai
`cascadeOnDelete` (bukan restrict) karena menghapus KSM juga meng-cascade penggunanya.

## 4. Aturan kanonik (satu sumber, dipakai di mana-mana)

| Aturan | Tempat | Inti |
|---|---|---|
| Hari desa | `App\Support\Hari` | `Hari::ini()` / `Hari::tanggal($mundur)`. Bandingkan kolom `date` sebagai **string `Y-m-d`**, jangan sebagai instant (beda 8 jam). |
| Nomor HP | `App\Support\NomorHp` | Normalisasi `+62/62/8…` → `08…`; valid `^08\d{8,11}$`. Unik dicek pada nilai **ternormalisasi**. |
| Akun warga | `App\Support\AkunWarga` | Satu-satunya pembuat/penaut akun warga. Nomor yang sudah dipakai di KSM = tautkan ke pemiliknya. |
| Sandi default desa | `Ksm::cocokSandiDefault`, hook `User::saving`, `Ksm::updated` | Hanya untuk warga. Admin/petugas tidak boleh memakainya. Naik peran dari warga **selalu** butuh sandi baru (hash tidak bisa dibandingkan dengan hash); di luar form → sandi acak (fail closed). |
| Status iuran | `App\Enums\StatusIuran::tentukan()` | Lunas/Kurang/Lebih/Belum/Nonaktif dari nominal tercatat. *Kebijakan sementara — keputusan pemilik masih TODO.* |
| Bulan yang boleh dicatat petugas | `App\Support\BulanIuran::pilihan()` | 6 bulan ke belakang s.d. 2 ke depan, hanya yang belum tercatat; dipakai sebagai opsi CheckboxList (rule `in`). |
| Status setor | `PembayaranIuran::statusSetor()`, `scopeBelumDisetor` | `petugas_id` null = langsung ke pengurus; terisi & `setoran_id` null = **belum disetor**; keduanya terisi = disetor. Lunas **tidak** bergantung pada setoran. |
| Jendela koreksi petugas | `Pengangkutan::bisaDiubahPetugas`, `PembayaranIuranPolicy` | Pengangkutan: sampai akhir hari berikutnya. Pembayaran: sampai masuk setoran. |

## 5. Peran & akses

| | Admin desa / pusat | Petugas | Warga |
|---|---|---|---|
| Pengguna (admin & petugas saja) | kelola | – | – |
| Pelanggan (+ akun warga: foto, HP, email, sandi, bisa login) | kelola | lihat pelanggan tugasnya, tanpa uang | – |
| Pembayaran iuran | semua; hapus hanya yang belum disetor | "Iuran diterima": miliknya, ubah/hapus sampai disetor | riwayat sendiri di dasbor |
| Terima iuran (widget Tugas hari ini) | – | rumah tugasnya yang aktif | – |
| Pengangkutan | semua | miliknya; ubah sampai akhir hari berikutnya | riwayat rumahnya di dasbor |
| Setoran petugas | kelola, batalkan | – | – |
| Profil KSM (nama, logo, latar login, sandi default) | ya | – | – |

Warga hanya melihat dasbornya (status iuran bulan ini, kontak petugas, riwayat iuran & pengangkutan).

## 6. Konvensi kode

- **Nama domain berbahasa Indonesia** (model, kolom, method: `pelanggan`, `nomor_urut`, `bisaDiubahPetugas`); komentar/PHPDoc bahasa Inggris.
  Tabel bentuk tunggal Indonesia + `#[Table('...')]` (generator membuat jamak — ganti).
- Atribut model: `#[Fillable]`, `#[Hidden]`, `#[Table]`. Kolom yang tidak boleh dari input (`ksm_id`, `user_id`,
  `petugas_id` pembayaran, `setoran_id`, `is_super_admin`) **tidak fillable** — isi lewat relasi/`forceFill`.
- Resource **satu file** + folder `Pages/` (bukan `Schemas/`/`Tables/` hasil generator). Aksi baris di
  **`ActionGroup`** (menu titik tiga). Halaman pengaturan dibagi **`Section`**.
- Query resource per peran di `getEloquentQuery()` (admin semua / petugas miliknya / lainnya `whereRaw('1 = 0')`) **plus** policy.
- Select/CheckboxList: opsi = himpunan yang sah → rule `in` bawaan Filament menolak nilai lain di server.
  Sertakan nilai tersimpan di opsi (mis. `PilihanPengguna::petugas($tersimpan)`) agar simpan tanpa perubahan tidak gagal.
- Tampilan panel (logo + nama, logo besar & latar di login) satu tempat: `App\Filament\Shared\TampilanPanel`; data logo/latar
  lewat trait `App\Models\Concerns\PunyaTampilanLogin` (dipakai `Ksm` dan `PengaturanMaster`; halaman master **Tampilan**).
- Berkas: avatar di disk `local` (privat, URL bertanda tangan); logo & latar login di disk `public`;
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
| SelectFilter `->relationship()` | opsi dimuat dari seluruh tabel relasi (bergantung pada scope tenant implisit) | opsi eksplisit `whereBelongsTo(Filament::getTenant())` |

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

**Menunggu keputusan pemilik:** kebijakan `StatusIuran::tentukan()` (kurang/lebih/nonaktif), setelan email (`MAIL_MAILER=log`).
**Berikutnya:** widget dasbor master & desa + halaman laporan mengikuti mockup
"KSM Circular Economy Dashboard" (rencana dulu sebelum eksekusi).
