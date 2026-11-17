# Blueprint: Peran pengguna, detail pelanggan, dan pengangkutan harian

Status: **Fase 1, 2 & 3 SELESAI (2026-11-17)** — Fase 1 a5a60cc (+§3.8, §3.9, fix e50f745); Fase 2 menurut §4.0; Fase 3 menurut §5 dengan catatan implementasi di bawah. Pengamanan tambahan: akun yang masih memakai sandi default desa tidak boleh naik ke admin/petugas tanpa sandi baru (User saving hook + PenggunaForm + Ksm::updated hanya warga). Disusun dengan skill `planning-filament` (Filament Blueprint v2.4).
Versi terpasang yang diverifikasi: Laravel 13.42.0 · Filament 5.11.4 · Livewire 4.4.8 · PHPUnit 12 (bukan Pest).
Sumber verifikasi: Boost `search-docs` + kode sumber terpasang di `vendor/` (rujukan baris disebut di tiap bagian).

## 0. Keputusan yang sudah disepakati

| Topik | Keputusan |
|---|---|
| Peran | `admin`, `petugas` (pengangkut), `warga`. Satu peran per pengguna. Super admin pusat tetap terpisah. |
| Pengguna per KSM | Setiap pengguna milik **satu** KSM (`users.ksm_id`). Pivot `ksm_user` dihapus. Pengguna desa tidak pernah melihat desa lain (menu ganti-tenant disembunyikan). |
| Siapa menambah pengguna | Super admin di `ksmdesa.my.id/admin` (KSM → tab Pengguna) **dan** admin desa di panel desanya (menu Pengguna). |
| Login | Di subdomain desa. Pengguna desa: **nomor HP + kata sandi**. Email di subdomain **khusus super admin**. |
| Nomor HP | Unik **per KSM** (nomor yang sama boleh jadi akun terpisah di KSM lain). |
| Hapus pengguna | **Tidak ada hapus permanen** — hanya nonaktifkan (tidak bisa login, riwayat tetap). |
| Pengangkutan | **Per rumah**: tiap rumah berstatus diangkut / tidak ada sampah / tidak bisa diakses + perkiraan kg. Total harian dihitung. |
| Hak petugas | Hanya pelanggan yang ditugaskan kepadanya; mengubah/menghapus catatannya sendiri sampai **H+1** (tanggal catatan = hari ini atau kemarin). |
| Akun warga ↔ pelanggan | Satu akun warga boleh terhubung ke **beberapa** pelanggan (`pelanggan.user_id`). |
| Warga melihat petugas | Nama + nomor HP petugas yang ditugaskan ke rumahnya. |
| Logo desa | Diunggah super admin (form KSM) **dan** admin desa (halaman "Profil KSM"). Tampil di halaman login subdomain dan di aplikasi desa. |
| Zona waktu "hari ini" | **Asumsi: WITA (`Asia/Makassar`)** via `APP_ZONA_WAKTU`. Ubah satu baris `.env` bila desa di WIB. Penyimpanan timestamp tetap UTC. |

> **Keputusan pemilik 2026-11-17 (setelah Fase 3):** warga SELALU lahir dari Pelanggan (akunnya dibuat/ditautkan otomatis); menu Pengguna (desa & master KSM → Pengguna) hanya untuk admin & petugas — akun warga tidak tampil dan peran warga ditolak di sana. Foto, nomor HP, email, kata sandi, dan "akun bisa login" akun warga diubah dari form Pelanggan; field itu hanya berlaku untuk akun yang sedang ditampilkan (atau akun baru saat create), tidak pernah ke akun orang lain yang ditautkan lewat nomor, dan tidak pernah ke akun admin/petugas yang juga pelanggan. Aksi baris semua resource memakai menu titik tiga (ActionGroup); Profil KSM, form KSM master, dan Profil saya memakai Section.

## Fase eksekusi

Tiap fase selesai dengan: test hijau (juga dengan `route:cache`), Pint, audit `filament-security-audit`, commit, deploy, cek produksi.

- **Fase 1** — Pengguna per KSM, peran, login nomor HP, manajemen pengguna (master + desa), logo & Profil KSM, menu tenant.
- **Fase 2** — Detail pelanggan (alamat, lokasi, kontak), penugasan petugas, akun warga ↔ pelanggan, hak lihat petugas.
- **Fase 3** — Pengangkutan harian per rumah, dasbor per peran (admin / petugas / warga).

---

## 1. Commands

Semua dijalankan dari root aplikasi.

```
# Fase 1
php artisan make:enum Enums/PeranPengguna --string --no-interaction
php artisan make:migration ubah_users_jadi_per_ksm --table=users --no-interaction
php artisan make:migration add_logo_to_ksm_table --table=ksm --no-interaction
php artisan make:filament-resource User --panel=ksm --simple --record-title-attribute=name --no-interaction
php artisan make:policy UserPolicy --model=User --no-interaction
php artisan make:policy PembayaranIuranPolicy --model=PembayaranIuran --no-interaction
php artisan make:policy TarifLayananPolicy --model=TarifLayanan --no-interaction

# Fase 2
php artisan make:migration add_detail_to_pelanggan_table --table=pelanggan --no-interaction
php artisan make:policy PelangganPolicy --model=Pelanggan --no-interaction

# Fase 3
php artisan make:enum Enums/StatusPengangkutan --string --no-interaction
php artisan make:model Pengangkutan --migration --factory --no-interaction
php artisan make:model PengangkutanRincian --migration --factory --no-interaction
php artisan make:filament-resource Pengangkutan --panel=ksm --record-title-attribute=tanggal --no-interaction
php artisan make:policy PengangkutanPolicy --model=Pengangkutan --no-interaction
php artisan make:filament-widget OperasionalStatsOverview --panel=ksm --stats-overview --no-interaction
php artisan make:filament-widget BeratPerHariChart --panel=ksm --chart --no-interaction
php artisan make:filament-widget TugasPetugasStatsOverview --panel=ksm --stats-overview --no-interaction
php artisan make:filament-widget DaftarTugasHariIni --panel=ksm --table --no-interaction
php artisan make:filament-widget WargaStatsOverview --panel=ksm --stats-overview --no-interaction
php artisan make:filament-widget RiwayatIuranSaya --panel=ksm --table --no-interaction
php artisan make:filament-widget RiwayatPengangkutanSaya --panel=ksm --table --no-interaction
```

> **Catatan implementasi Fase 3 (2026-11-17):** `make:filament-widget` dengan flag tipe (`--stats-overview`,
> `--chart`, `--table`) di versi terpasang menghasilkan `Widget` biasa + view dengan nama bersufiks ganda
> (mis. `BeratPerHariChartChart`) — ketujuh widget ditulis manual. Resource ditulis manual mengikuti pola file
> tunggal proyek (`Pengangkutans/PengangkutanResource.php` + `Pages/`). `petugas_id` pada `pengangkutan` memakai
> `cascadeOnDelete` (bukan restrict): pengguna tak pernah dihapus, dan restrict akan menggagalkan hapus KSM yang
> juga meng-cascade pengguna. DatePicker menyerahkan nilai `Y-m-d H:i:s` ke rule — aturan "satu ronde per hari"
> membandingkan `Carbon::parse($value)->toDateString()`.

File yang dibuat **manual** (tanpa generator): `app/Support/KsmDariHost.php`, `app/Support/NomorHp.php`,
`app/Support/Hari.php`, `app/Filament/Ksm/Pages/Auth/Login.php`, `app/Filament/Ksm/Pages/Tenancy/EditProfilKsm.php`,
`app/Filament/Shared/PenggunaForm.php`.

Setiap migrasi pada tabel/model baru: ganti nama tabel ke bentuk tunggal Indonesia (`pengangkutan`,
`pengangkutan_rincian`) seperti konvensi proyek (`#[Table('...')]` di model), sama seperti `pelanggan`.

---

## 2. Aturan kanonik bersama (dipakai di banyak tahap)

Bagian ini mencegah aturan yang "masuk akal tapi berbeda" di tahap yang berbeda (pratinjau, validasi, simpan, muat ulang).

### 2.1 `App\Support\KsmDariHost` — KSM dari subdomain
- **Rule:** `slug = Str::beforeLast($host, '.'.config('app.domain'))`; hasil `null` bila `slug === $host` (apex atau host asing),
  bila slug berisi titik (lebih dari satu label), atau bila tak ada `Ksm` dengan slug itu. Hasil di-memo per request
  (`once()` atau properti statis per host).
- **API:** `KsmDariHost::dari(string $host): ?Ksm`, `KsmDariHost::dariRequest(): ?Ksm` (= `dari(request()->getHost())`).
- **Dipakai di (semua harus memanggil helper ini, jangan menulis ulang):**
  1. `App\Http\Middleware\EnsureKsmSubdomain` (refactor: `abort_if(KsmDariHost::dariRequest() === null, 404)`).
  2. `Login::getCredentialsFromFormData()` (scope `ksm_id`) dan `Login::getHeading()`.
  3. `KsmPanelProvider` → `brandName()` dan `brandLogo()` saat `Filament::getTenant()` masih `null` (halaman login).
  4. `User::getDefaultTenant()` untuk super admin.
- **Consistency check:** di `zzz.ksmdesa.my.id` middleware 404 sebelum login dirender, jadi `Login` tak pernah menerima
  KSM `null` dari host yang sah; tetap tulis kode login aman terhadap `null` (kondisi `ksm_id = null` tidak cocok dengan pengguna desa mana pun).

### 2.2 `App\Support\NomorHp` — normalisasi nomor HP
- **Rule:** buang semua non-digit; awalan `62` → `0`; awalan `8` → `08…`; hasil valid bila cocok `^08\d{8,11}$`
  (10–13 digit). Contoh: `+62 812-3456-7890` → `081234567890`.
- **API:** `NomorHp::normalisasi(?string): ?string` (null/blank → null), `NomorHp::valid(string $normal): bool`.
- **Dipakai di:** validasi + dehydrate field `no_hp` pengguna (§4.2), field `no_hp` pelanggan (§5.1), kredensial login (§3.2).
- **Consistency check (wajib):** cek keunikan dilakukan pada nilai **ternormalisasi**, bukan input mentah. Kalau tidak,
  `+62812…` lolos cek unik lalu disimpan sebagai `0812…` dan bentrok di indeks unik DB. Karena itu keunikan memakai
  **closure rule** yang menormalkan dulu (bukan `->scopedUnique()` yang memeriksa nilai mentah).

### 2.3 `App\Support\Hari` — "hari ini" menurut desa
- **Rule:** `Hari::ini(): CarbonImmutable` = `now(config('app.zona_waktu_lokal'))->startOfDay()`;
  `config/app.php`: `'zona_waktu_lokal' => env('APP_ZONA_WAKTU', 'Asia/Makassar')`. `app.timezone` tetap `UTC`.
  Kolom `date` (`tanggal`) dibandingkan sebagai string `Y-m-d` terhadap `Hari::ini()->toDateString()`.
- **Dipakai di:** default/min/max `tanggal` pengangkutan, jendela ubah petugas (§2.4), widget "hari ini"/"bulan ini", status iuran "bulan ini" untuk warga.

### 2.4 Jendela ubah petugas
- **Rule:** `Pengangkutan::bisaDiubahPetugas(): bool` = `$this->tanggal->toDateString() >= Hari::ini()->subDay()->toDateString()`.
- **Stages:** (a) tombol Edit/Delete disembunyikan lewat policy; (b) halaman Edit langsung lewat URL → policy `update` menolak (403);
  (c) membuat catatan baru dengan `tanggal` < kemarin → validasi server menolak (bukan hanya `minDate` UI).
- **Consistency check:** catatan bertanggal kemarin bisa diubah hari ini, **tidak** bisa diubah besok — uji dengan `travelTo` di zona WITA.

### 2.5 Berat hanya untuk status "diangkut"
- **Rule:** `StatusPengangkutan::butuhBerat(): bool` → `true` hanya untuk `Diangkut`. Model `PengangkutanRincian` hook `saving`:
  bila `! $this->status->butuhBerat()` maka `$this->perkiraan_kg = null` (kanonik di level model, tak bergantung UI).
- **Alasan:** field yang `visible(false)` **tidak di-dehydrate** oleh Filament → pada edit, mengganti status dari diangkut ke
  "tidak ada sampah" akan meninggalkan kg lama di DB kalau hanya mengandalkan form. Hook model menutup celah itu.

---

## 3. Fase 1 — Pengguna per KSM, peran, login, logo

### 3.1 Models

```
Enum: App\Enums\PeranPengguna  (string-backed)
  Implements: Filament\Support\Contracts\HasLabel, HasColor, HasIcon
  Cases:
    - Admin = 'admin'      label "Admin"              color "primary" icon Heroicon::OutlinedShieldCheck
    - Petugas = 'petugas'  label "Petugas pengangkut" color "warning" icon Heroicon::OutlinedTruck
    - Warga = 'warga'      label "Warga"              color "gray"    icon Heroicon::OutlinedHome
```

```
Migration: ubah_users_jadi_per_ksm
  Table: users
  Add: ksm_id (bigint, nullable, foreign(ksm.id), cascadeOnDelete) — after id
  Add: peran (string(20), nullable)           — null hanya untuk super admin
  Add: no_hp (string(20), nullable)
  Add: aktif (boolean, default:true)
  Modify: email → nullable (->nullable()->change())
  Remove: unique index users_email_unique
  Add: unique(['ksm_id','email'])  name users_ksm_email_unique
  Add: unique(['ksm_id','no_hp'])  name users_ksm_no_hp_unique
  Data (dalam up(), SEBELUM drop pivot):
    untuk tiap baris ksm_user: jika user.is_super_admin → lewati (tetap global, ksm_id null);
    selain itu set users.ksm_id = baris.ksm_id, users.peran = 'admin' (baris pertama menang;
    log peringatan bila user non-super-admin punya >1 KSM). Produksi saat ini: 0 baris.
  Then: Schema::dropIfExists('ksm_user')
  down(): buat ulang ksm_user, salin (ksm_id,user_id) dari users yang ksm_id-nya terisi, kembalikan indeks email unik
          (hanya bila tak ada duplikat), hapus kolom tambahan.
  Catatan MySQL: unique dengan ksm_id NULL mengizinkan duplikat → keunikan email super admin dijaga di app:super-admin.
```

```
Migration: add_logo_to_ksm_table
  Table: ksm
  Add: logo (string, nullable) — path relatif di disk 'public', folder 'logo-ksm'
```

```
Model: App\Models\User (update)
  Fillable: name, email, no_hp, password, peran, aktif
    (TIDAK fillable: is_super_admin, ksm_id — ksm_id diisi lewat relasi/asosiasi tenant Filament)
  Casts: peran → PeranPengguna::class, aktif → boolean, is_super_admin → boolean (tetap: email_verified_at, password hashed)
  $attributes: is_super_admin=false, aktif=true
  Implements: FilamentUser, HasTenants, Filament\Models\Contracts\HasDefaultTenant
  Relationships:
    - belongsTo: Ksm via ksm_id                      (ksm())
    - hasMany: Pelanggan via user_id                 (pelangganMilik())   — Fase 2
    - hasMany: Pelanggan via petugas_id              (pelangganTugas())   — Fase 2
    - hasMany: Pengangkutan via petugas_id           (pengangkutan())     — Fase 3
    HAPUS: belongsToMany ksm() lama.
  Methods:
    isAdminKsm(): bool   = is_super_admin || peran === Admin
    isPetugas(): bool    = ! is_super_admin && peran === Petugas
    isWarga(): bool      = ! is_super_admin && peran === Warga
    canAccessPanel(Panel): match id:
      'admin' → is_super_admin && aktif
      'ksm'   → aktif && (is_super_admin || (ksm_id !== null && peran !== null))
      default → false
      (Filament memanggil ini sebelum login berhasil — di dalam Timebox, Login.php:106 — dan pada setiap request,
       jadi akun yang dinonaktifkan langsung keluar pada request berikutnya.)
    getTenants(Panel): is_super_admin ? Ksm::orderBy('nama')->get() : collect(array_filter([$this->ksm]))
    canAccessTenant(Model $tenant): is_super_admin || ($this->ksm_id !== null && $this->ksm_id === $tenant->getKey())
      SECURITY: satu-satunya pertahanan terhadap manipulasi URL tenant.
    getDefaultTenant(Panel): is_super_admin ? (KsmDariHost::dariRequest() ?? Ksm::orderBy('nama')->first()) : $this->ksm
      Alasan: tanpa ini, super admin yang login di makmurjaya diarahkan ke KSM pertama abjad = subdomain lain tanpa sesi
      (FilamentManager::getUserDefaultTenant() memanggil getDefaultTenant() lebih dulu, baris 595–605).
```

```
Model: App\Models\Ksm (update)
  Fillable: + logo
  Relationships: users() → hasMany(User) (ganti dari belongsToMany)
  Method: logoUrl(): ?string = $this->logo ? Storage::disk('public')->url($this->logo) : null
  Events (booted):
    updated: jika logo berubah dan nilai lama tidak null → hapus berkas lama dari disk 'public'
    deleted: hapus berkas logo bila ada
```

### 3.2 Login subdomain — `App\Filament\Ksm\Pages\Auth\Login`
- Location: `app/Filament/Ksm/Pages/Auth/Login.php`, `extends Filament\Auth\Pages\Login`
- Docs: https://filamentphp.com/docs/5.x/users/overview#customizing-the-authentication-features ·
  https://laravel.com/docs/13.x/authentication#specifying-additional-conditions
- Register: `KsmPanelProvider` → `->login(App\Filament\Ksm\Pages\Auth\Login::class)`
- Overrides (diverifikasi terhadap `vendor/filament/filament/src/Auth/Pages/Login.php`):
  - `form(Schema $schema)`: components `[ $loginField, $this->getPasswordFormComponent(), $this->getRememberFormComponent() ]`
    ```
    Field: login
      Component: Filament\Forms\Components\TextInput
      Docs: https://filamentphp.com/docs/5.x/forms/text-input
      Validation: required, max:255
      Config: ->label('Nomor HP'), ->helperText('Super admin: gunakan email.'), ->autocomplete('username'), ->autofocus()
    ```
  - `getCredentialsFromFormData(array $data): array` → `['password' => $data['password'], <closure>]`, di mana closure
    (didukung `EloquentUserProvider::retrieveByCredentials`, baris 131):
    - input mengandung `@` → `where('email', mb_strtolower(trim(input)))->where('is_super_admin', true)->whereNull('ksm_id')`
    - selain itu → `where('no_hp', NomorHp::normalisasi(input))->where('ksm_id', KsmDariHost::dariRequest()?->getKey())`
  - `throwFailureValidationException(): never` → `ValidationException::withMessages(['data.login' => 'Nomor HP/email atau kata sandi salah.'])`
    (bawaan memakai kunci `data.email`, baris 253 — harus diganti, kalau tidak pesan galat tak tampil).
  - `getHeading()` → nama KSM dari `KsmDariHost::dariRequest()` (fallback 'Masuk').
- Lifecycle (bawaan, dipertahankan): rate limit 5/menit → `retrieveByCredentials` → `validateCredentials` →
  `canAccessPanel` (akun nonaktif gagal dengan pesan generik yang sama) → login.
- Rule: pengguna desa **hanya** dengan nomor HP di KSM subdomain itu; email hanya mencocokkan super admin.
  Konsekuensi: nomor HP sama di KSM lain tidak pernah tertukar karena kondisi `ksm_id`.

### 3.3 Panel `ksm` — `App\Providers\Filament\KsmPanelProvider` (update)
```
Config:
  ->login(App\Filament\Ksm\Pages\Auth\Login::class)
  ->brandName(fn (): string => (Filament::getTenant() ?? KsmDariHost::dariRequest())?->nama ?? 'KSM Desa')
  ->brandLogo(fn (): ?string => (Filament::getTenant() ?? KsmDariHost::dariRequest())?->logoUrl())
      (logo null → Filament menampilkan brandName teks; dipakai di halaman login `header/simple.blade.php`,
       sidebar & topbar — semuanya merender `<x-filament-panels::logo />`)
  ->brandLogoHeight('2.75rem')
  ->tenantMenu(fn (): bool => (bool) auth()->user()?->is_super_admin)   // pengguna desa tak melihat desa lain
  ->tenantProfile(App\Filament\Ksm\Pages\Tenancy\EditProfilKsm::class)
  ->navigationItems([
      Filament\Navigation\NavigationItem::make('Profil KSM')
        ->icon(Heroicon::OutlinedIdentification)->group('Data Master')->sort(90)
        ->url(fn (): string => EditProfilKsm::getUrl())
        ->visible(fn (): bool => (bool) auth()->user()?->isAdminKsm()),
  ])   // perlu karena menu tenant (tempat tautan profil bawaan) disembunyikan
Docs: https://filamentphp.com/docs/5.x/users/tenancy · https://filamentphp.com/docs/5.x/styling/overview#adding-a-logo ·
      https://filamentphp.com/docs/5.x/navigation/overview#registering-custom-navigation-items
```

### 3.4 Halaman Profil KSM — `App\Filament\Ksm\Pages\Tenancy\EditProfilKsm`
- Location: `app/Filament/Ksm/Pages/Tenancy/EditProfilKsm.php`, `extends Filament\Pages\Tenancy\EditTenantProfile`
- Docs: https://filamentphp.com/docs/5.x/users/tenancy#adding-a-tenant-profile-page
- `getLabel()`: 'Profil KSM'; `canView(Model $tenant)`: `auth()->user()->isAdminKsm()`
- Form (Columns: 1):
  ```
  Field: nama
    Component: Filament\Forms\Components\TextInput
    Docs: https://filamentphp.com/docs/5.x/forms/text-input
    Validation: required, max:255
    Config: ->label('Nama KSM')
  Field: logo   (spesifikasi sama dengan §3.6 "Field: logo")
  ```
  Slug/subdomain **tidak** ada di sini (hanya master yang boleh mengubah alamat).

### 3.5 Form pengguna bersama — `App\Filament\Shared\PenggunaForm`
- Location: `app/Filament/Shared/PenggunaForm.php`; `public static function komponen(Closure $ksm): array` di mana
  `$ksm` mengembalikan `Ksm` konteks (RM master: `fn () => $this->getOwnerRecord()`; panel desa: `fn () => Filament::getTenant()`).
- Imports: `Filament\Schemas\Components\Utilities\Get`, `Filament\Schemas\Components\Utilities\Set`
```
Field: name
  Component: Filament\Forms\Components\TextInput
  Docs: https://filamentphp.com/docs/5.x/forms/text-input
  Validation: required, max:255
  Config: ->label('Nama')

Field: no_hp
  Component: Filament\Forms\Components\TextInput
  Docs: https://filamentphp.com/docs/5.x/forms/text-input
  Validation: required, max:20, closure rule:
    $n = NomorHp::normalisasi($value); gagal 'Nomor HP tidak valid (contoh 0812xxxxxxxx).' bila ! NomorHp::valid($n);
    gagal 'Nomor HP sudah dipakai pengguna lain di KSM ini.' bila
    User::query()->where('ksm_id', $ksm()->getKey())->where('no_hp', $n)->when($record, whereKeyNot)->exists()
  Config: ->label('Nomor HP'), ->tel(), ->dehydrateStateUsing(fn (?string $state): ?string => NomorHp::normalisasi($state))

Field: email
  Component: Filament\Forms\Components\TextInput
  Docs: https://filamentphp.com/docs/5.x/forms/text-input
  Validation: nullable, email, max:255, closure rule unik per KSM (bandingkan mb_strtolower(trim))
  Config: ->email(), ->helperText('Opsional. Login pengguna desa memakai nomor HP.'),
          ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null)

Field: peran
  Component: Filament\Forms\Components\ToggleButtons
  Docs: https://filamentphp.com/docs/5.x/forms/toggle-buttons
  Validation: required, enum PeranPengguna
  Config: ->options(PeranPengguna::class), ->inline(), ->default(PeranPengguna::Warga),
          ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
          (field disabled TIDAK di-dehydrate oleh Filament → admin tak bisa mengubah perannya sendiri dari server-side,
           mencegah KSM kehilangan admin terakhir)

Field: aktif
  Component: Filament\Forms\Components\Toggle
  Docs: https://filamentphp.com/docs/5.x/forms/toggle
  Validation: boolean
  Config: ->default(true), ->helperText('Nonaktif = tidak bisa login; riwayat tetap tersimpan.'),
          ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)

Field: password
  Component: Filament\Forms\Components\TextInput
  Docs: https://filamentphp.com/docs/5.x/forms/text-input#password-input
  Validation: required on create, nullable on edit, min:8, max:255
  Config: ->label('Kata sandi'), ->password(), ->revealable(),
          ->required(fn (string $operation): bool => $operation === 'create'),
          ->dehydrated(fn (?string $state): bool => filled($state)),
          ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Kosongkan bila tidak diganti.' : null)
```
Layout: Columns 2 (`name`, `no_hp` | `email`, `password` | `peran` columnSpan full | `aktif`).

### 3.6 Resources

```
Resource: App\Filament\Resources\Ksms\KsmResource (master, UPDATE)
  Form: tambah
    Field: logo
      Component: Filament\Forms\Components\FileUpload
      Docs: https://filamentphp.com/docs/5.x/forms/file-upload
      Validation: nullable, image, mimetypes:image/png,image/jpeg,image/webp, max:1024 (KB)
      Config: ->image(), ->acceptedFileTypes(['image/png','image/jpeg','image/webp']), ->disk('public'),
              ->directory('logo-ksm'), ->visibility('public'), ->maxSize(1024), ->imageEditor(false),
              ->preventFilePathTampering(), ->helperText('PNG/JPG/WebP, maks 1 MB. Tampil di halaman login & aplikasi desa.')
      (nama berkas acak bawaan dipertahankan — JANGAN preserveFilenames; folder storage/app/public sudah
       menolak eksekusi skrip lewat .htaccess)
  Table: tambah
    Column: logo
      Component: Filament\Tables\Columns\ImageColumn
      Docs: https://filamentphp.com/docs/5.x/tables/columns/image
      Config: ->disk('public'), ->circular(), ->label('')
    Ubah: users_count → label 'Pengguna'

RelationManager: App\Filament\Resources\Ksms\RelationManagers\UsersRelationManager (master, GANTI ISI)
  Relationship: users (hasMany User) — bukan lagi belongsToMany
  Title: 'Pengguna'
  Form: PenggunaForm::komponen(fn () => $this->getOwnerRecord())
  Table:
    Column: name — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->searchable()
    Column: no_hp — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->searchable()
    Column: peran — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->badge()
    Column: aktif — Filament\Tables\Columns\IconColumn — https://filamentphp.com/docs/5.x/tables/columns/icon — ->boolean()
  Filter: peran — Filament\Tables\Filters\SelectFilter — https://filamentphp.com/docs/5.x/tables/filters/select — ->options(PeranPengguna::class)
  Actions: Filament\Actions\CreateAction (label 'Tambah pengguna'), Filament\Actions\EditAction
  HAPUS: AttachAction, DetachAction, DeleteAction (pengguna tak pernah dihapus/dipindah KSM)
  Authorization: panel master hanya super admin (canAccessPanel 'admin').
```

```
Resource: App\Filament\Ksm\Resources\Users\UserResource (panel desa, BARU)
  Command: php artisan make:filament-resource User --panel=ksm --simple --record-title-attribute=name --no-interaction
  Location: App\Filament\Ksm\Resources\Users\UserResource
  Docs: https://filamentphp.com/docs/5.x/resources/overview
  Tenancy: ownership relationship 'ksm' (BelongsTo) — Filament mengasosiasikan tenant saat create;
           $tenantRelationshipName = 'users'
  Labels: $modelLabel 'pengguna', $pluralModelLabel 'pengguna'
  Navigation: Group 'Data Master', Icon Heroicon::OutlinedUsers, Sort 5
  Form: PenggunaForm::komponen(fn () => Filament::getTenant())
  Table:
    Column: name — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->searchable(), ->sortable()
    Column: no_hp — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->searchable()
    Column: peran — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->badge()   (warna & ikon dari enum)
    Column: aktif — Filament\Tables\Columns\IconColumn — https://filamentphp.com/docs/5.x/tables/columns/icon — ->boolean()
    Column: pelanggan — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->label('Pelanggan'),
            ->state(fn (User $r): ?int => match ($r->peran) { Petugas => $r->pelanggan_tugas_count, Warga => $r->pelanggan_milik_count, default => null }),
            ->placeholder('—')   (Fase 2; modifyQueryUsing withCount(['pelangganTugas','pelangganMilik']))
  Filters:
    Filter: peran — Filament\Tables\Filters\SelectFilter — https://filamentphp.com/docs/5.x/tables/filters/select — ->options(PeranPengguna::class)
    Filter: aktif — Filament\Tables\Filters\TernaryFilter — https://filamentphp.com/docs/5.x/tables/filters/ternary — ->default(true)
  Actions:
    Action: EditAction — Filament\Actions\EditAction — table row — Authorization: UserPolicy::update
    Action: alihAktif
      Component: Filament\Actions\Action
      Docs: https://filamentphp.com/docs/5.x/actions/overview
      Location: table row
      Visibility: hidden untuk baris milik pengguna yang sedang login
      Authorization: ->authorize('update')  (UserPolicy::update)
      Behavior: 1) requiresConfirmation 2) $record->update(['aktif' => ! $record->aktif])
                3) label/ikon/warna mengikuti status ('Nonaktifkan' danger / 'Aktifkan' success)
  Tidak ada DeleteAction / DeleteBulkAction.
```

```
Command (UPDATE): App\Console\Commands\MakeSuperAdmin
  - cari/buat user dengan email (lowercase) DAN ksm_id null; tolak bila email itu milik user desa
  - set is_super_admin = true, ksm_id = null, peran = null, aktif = true
```

### 3.7 Authorization (Fase 1)
```
Policy: App\Policies\UserPolicy  — Docs: https://filamentphp.com/docs/5.x/resources/overview#authorization
  viewAny: user->isAdminKsm()
  view:    user->isAdminKsm() AND (user.is_super_admin OR model.ksm_id === user.ksm_id)
  create:  user->isAdminKsm()
  update:  sama dengan view
  delete, deleteAny, forceDelete, forceDeleteAny, restore, restoreAny: false (hanya nonaktifkan)

Policy: App\Policies\PembayaranIuranPolicy
  viewAny/view/create/update/delete: user->isAdminKsm();  deleteAny: false

Policy: App\Policies\TarifLayananPolicy
  viewAny/view/create/update/delete: user->isAdminKsm();  deleteAny: false

Page: App\Filament\Ksm\Pages\RekapIuran — tambah canAccess(): user->isAdminKsm()
Widgets lama: IuranStatsOverview, IuranPerBulanChart — tambah static canView(): user->isAdminKsm()
```
Catatan lifecycle: halaman resource memanggil `authorizeAccess()` di `mount()` dan mengulang cek pada request Livewire;
policy menyembunyikan navigasi otomatis. Widget tabel me-query ulang tiap request (tidak menyimpan model di properti).

### 3.8 Amandemen (2026-11-17, permintaan pemilik): manajemen administrator pusat + pengaman Fase 1

**a. Administrator pusat (super admin) dikelola dari master.** Super admin bisa menambah super admin lain.
```
Resource: App\Filament\Resources\Users\UserResource (panel admin/master, BARU)
  Command: php artisan make:filament-resource User --panel=admin --simple --record-title-attribute=name --no-interaction
  Labels: $modelLabel 'administrator', $pluralModelLabel 'administrator', navigation label 'Administrator'
  Navigation: Icon Heroicon::OutlinedShieldCheck, Sort 90
  getEloquentQuery(): parent->where('is_super_admin', true)->whereNull('ksm_id')
  Form (Columns 2):
    Field: name — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: required, max:255
    Field: email — Filament\Forms\Components\TextInput — Validation: required, email, max:255, closure unik di antara super admin
           (whereNull ksm_id, bandingkan lowercase, abaikan record) — Config: ->email(), ->dehydrateStateUsing(lowercase+trim)
    Field: password — sama dengan §3.5 (min 8; wajib saat create)
    Field: aktif — Filament\Forms\Components\Toggle — https://filamentphp.com/docs/5.x/forms/toggle — Config: ->default(true),
           ->disabled(fn (?User $record) => $record?->is(auth()->user()) ?? false)   (tak bisa menonaktifkan diri sendiri)
  Create: CreateAction ->using(fn (array $data): User => tap(new User($data))->forceFill(['is_super_admin' => true])->save())
          (is_super_admin tidak fillable; diset server-side)
  Table: name (->searchable()), email (->searchable()), aktif (IconColumn ->boolean()), created_at (->date())
  Actions: EditAction; alihAktif (sama dengan §3.6, tersembunyi untuk diri sendiri). Tanpa hapus.
  Authorization: UserPolicy (super admin lolos semua cek isAdminKsm()).
```

**b. Pengaman antar-fase.** Fase 1 sudah memungkinkan akun petugas/warga, sementara hak mereka baru diatur di Fase 2–3:
- `App\Policies\PelangganPolicy` dibuat di Fase 1 dengan semua ability = `isAdminKsm()` (Fase 2 melonggarkan untuk petugas).
- `Filament\Widgets\AccountWidget` ditambahkan ke dasbor desa untuk semua peran (dasbor petugas/warga tidak kosong).
- Login: bila input bukan email dan `NomorHp::normalisasi()` menghasilkan `null`, atau KSM host `null`, closure kredensial
  memakai kondisi mustahil (`whereRaw('1 = 0')`). Tanpa ini, `where('no_hp', null)` menjadi `whereNull` dan bisa mencocokkan
  pengguna desa yang belum punya nomor HP (mis. hasil migrasi pivot).

### 3.9 Amandemen: foto akun (dikerjakan di Fase 1)
- `users.avatar` (disk privat `local`, folder `avatar/`, URL bertanda tangan 1 jam via `temporaryUrl`); `User` implements `HasAvatar`.
- Diunggah admin (form pengguna desa & master) dan oleh pengguna sendiri di **Profil saya** (`/profil-saya`: foto + kata sandi;
  nama/HP/email/peran hanya tampil). Profil KSM pindah ke slug `/profil-ksm` (slug bawaan `profile` bentrok dengan profil pengguna).
- Avatar inisial digambar lokal (`App\Filament\AvatarProviders\InisialAvatar`, SVG data URI) — bawaan Filament memanggil ui-avatars.com.
- Global `FileUpload::configureUsing(->preventFilePathTampering())` (disk privat juga berisi CSV buku iuran).

### 3.10 Keputusan untuk Fase 2 (dari pemilik, 2026-11-17)
- Foto di daftar pelanggan = foto **akun warga** yang terhubung.
- **Setiap pelanggan otomatis punya akun warga** yang langsung bisa login.
- **Kata sandi awal = sandi default per desa** (keputusan pemilik): admin desa mengaturnya di **Profil KSM**; akun warga yang
  dibuat otomatis memakai sandi itu. Rancangan: simpan sebagai hash (`ksm.sandi_default_hash`, tak pernah plaintext), akun baru
  mendapat hash yang sama; field kosong saat simpan = tidak diubah.
- Sub-keputusan yang ditanyakan di awal Fase 2: (a) pelanggan tanpa nomor HP — akun dibuat tapi belum bisa login, atau nomor HP
  wajib; (b) nomor HP sama di beberapa pelanggan = satu akun; (c) mengganti sandi default: hanya untuk akun baru, atau juga akun
  yang belum pernah ganti sandi; (d) rekomendasi keamanan: wajib ganti sandi saat login pertama (sandi default diketahui banyak orang).

---

## 4. Fase 2 — Detail pelanggan & penugasan petugas

### 4.0 Amandemen Fase 2 (keputusan pemilik 2026-11-17) — MENGGANTIKAN bagian §4 yang bertentangan

Keputusan: setiap pelanggan otomatis punya akun warga · tanpa nomor HP = akun dibuat, login nanti · sandi awal = sandi default
desa · tidak wajib ganti sandi · ganti sandi default berlaku untuk akun baru **dan** akun yang belum mengganti sandi ·
pelanggan nonaktif: akun tetap bisa login · nomor HP sama = satu akun (konsekuensi unik per KSM).

Perubahan terhadap §4.1/§4.3:
- **TIDAK ada kolom `pelanggan.no_hp`.** Nomor HP hanya di akun (`users.no_hp`, sumber tunggal untuk login & kontak). Form pelanggan
  punya field virtual `no_hp_akun` yang menulis ke akun lewat aturan kanonik di bawah. **TIDAK ada Select `user_id` manual.**
- Kolom baru: `ksm.sandi_default_hash` (string, nullable; hash, tak pernah plaintext) dan `users.pakai_sandi_default`
  (boolean, default false).
- Foto di daftar pelanggan = `akunWarga.avatar` (disk privat, signed) dengan inisial nama pelanggan sebagai cadangan.

Aturan kanonik `App\Support\AkunWarga` (satu-satunya tempat yang membuat/menghubungkan akun warga):
- `untukNomor(Ksm, ?string $nomor, string $nama): User` — nomor dinormalkan; bila sudah ada pengguna KSM itu dengan nomor tsb
  → kembalikan pengguna itu (peran apa pun); selain itu buat akun **warga** baru (`aktif`, `pakai_sandi_default = true`,
  password = `ksm.sandi_default_hash` atau hash acak tak terpakai bila default belum diatur, `no_hp` = nomor atau null).
- `sinkronkanNomor(Pelanggan, ?string $nomor)` (edit): nomor null/sama → tidak berubah; nomor milik pengguna lain di KSM →
  hubungkan pelanggan ke pengguna itu; akun sekarang warga & hanya milik pelanggan ini → ganti nomornya; selain itu → akun baru.
- Model `Pelanggan::created`: bila `user_id` masih null → `untukNomor(ksm, null, nama)` (menutup jalur impor/command).
- Form create (`CreateAction::using`) & edit (`EditAction::using`): ambil `no_hp_akun` dari data, panggil aturan di atas, simpan.
- Model `User::saving`: password berubah tanpa `pakai_sandi_default` ikut diubah → `pakai_sandi_default = false`
  (Profil saya & admin menyetel sandi → bukan default lagi).
- Model `Ksm::updated`: `sandi_default_hash` berubah → `users` KSM itu dengan `pakai_sandi_default = true` mendapat hash baru
  (query builder; tanpa event).
- Field sandi default (`App\Filament\Shared\SandiDefaultField`) di Profil KSM dan form KSM master: password, min 8,
  kosong = tidak diubah, disimpan sebagai `Hash::make`.
- Backfill: `php artisan app:akun-warga {ksm}` + aksi header "Buat akun warga yang belum ada" (admin) → idempoten.
- Pengaman salah ketik: helper text live pada `no_hp_akun` menyebut nama pemilik bila nomor sudah dipakai akun lain.


### 4.1 Model
```
Migration: add_detail_to_pelanggan_table
  Table: pelanggan
  Add: petugas_id (bigint, nullable, foreign(users.id), nullOnDelete)
  Add: user_id (bigint, nullable, foreign(users.id), nullOnDelete)     — akun warga pemilik
  Add: alamat (text, nullable)
  Add: rw (string(10), nullable)                                       — rt sudah ada
  Add: patokan (string(255), nullable)
  Add: no_hp (string(20), nullable)
  Add: latitude (decimal(10,7), nullable)
  Add: longitude (decimal(10,7), nullable)
  Add: catatan_pengangkutan (text, nullable)
  Add: index(['ksm_id','petugas_id']), index('user_id')

Model: App\Models\Pelanggan (update)
  Fillable: + petugas_id, user_id, alamat, rw, patokan, no_hp, latitude, longitude, catatan_pengangkutan
  Casts: latitude, longitude → 'decimal:7'
  Relationships:
    - belongsTo: User via petugas_id   (petugas())
    - belongsTo: User via user_id      (akunWarga())
    - hasMany: PengangkutanRincian via pelanggan_id   (rincianPengangkutan()) — Fase 3
  Methods:
    mapsUrl(): ?string — lat & lng ada → "https://www.google.com/maps/search/?api=1&query={lat},{lng}";
                         alamat ada → query=urlencode(alamat.', '.ksm.desa); selain itu null.
                         (skema https tetap; lat/lng tervalidasi numerik → aman dari skema berbahaya)
    punyaPetugasAktif(): bool — petugas !== null && petugas.aktif && petugas.peran === Petugas
  Scope: scopeTanpaPetugasAktif(Builder) — petugas_id null ATAU whereDoesntHave('petugas', aktif & peran petugas)
```

### 4.2 Pilihan petugas / akun warga — aturan "simpan tanpa perubahan"
- **Rule:** opsi Select = pengguna KSM ini (`whereBelongsTo(Filament::getTenant())`) dengan peran yang sesuai **dan aktif**,
  **ditambah nilai yang sedang tersimpan** walau sudah nonaktif/ganti peran (label diberi akhiran " (nonaktif)").
- **Alasan:** `Select` memvalidasi nilai terhadap opsinya di server (`Select::getInValidationRuleValues()`, baris 1776).
  Tanpa menyertakan nilai tersimpan, mengedit alamat pelanggan yang petugasnya sudah nonaktif akan gagal validasi.
- **Consistency check:** simpan pelanggan tanpa mengubah petugas (yang kini nonaktif) → sukses; memilih petugas KSM lain
  (ID dimanipulasi) → gagal validasi.

### 4.3 Resource: `App\Filament\Ksm\Resources\Pelanggans\PelangganResource` (UPDATE)
```
Form (admin; Columns 2 — Section memakai ColumnSpan full agar tidak 25%):
  Section 'Lokasi' — Filament\Schemas\Components\Section — https://filamentphp.com/docs/5.x/schemas/sections — ->columnSpanFull(), ->columns(2)
    Field: alamat — Filament\Forms\Components\Textarea — https://filamentphp.com/docs/5.x/forms/textarea — Validation: nullable, max:1000 — Config: ->rows(2), ->columnSpanFull()
    Field: rt (sudah ada, pindah ke section)
    Field: rw — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: nullable, max:10
    Field: patokan — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: nullable, max:255 — Config: ->placeholder('mis. depan masjid, pagar hijau'), ->columnSpanFull()
    Field: latitude — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: nullable, numeric, between:-90,90 — Config: ->numeric(), ->step('any')
    Field: longitude — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: nullable, numeric, between:-180,180 — Config: ->numeric(), ->step('any'),
           ->helperText('Dari Google Maps: tekan lama titik rumah, salin angka koordinat.')
  Section 'Kontak & pengangkutan' — ->columnSpanFull(), ->columns(2)
    Field: no_hp — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: nullable, max:20, closure: bila diisi harus NomorHp::valid(normalisasi)
           — Config: ->tel(), ->dehydrateStateUsing(fn (?string $s) => NomorHp::normalisasi($s))
    Field: petugas_id — Filament\Forms\Components\Select — https://filamentphp.com/docs/5.x/forms/select
           — Validation: nullable (in-rule otomatis dari opsi)
           — Config: ->label('Petugas pengangkut'), ->options(aturan §4.2 peran Petugas), ->searchable(), ->preload()
    Field: user_id — Filament\Forms\Components\Select — https://filamentphp.com/docs/5.x/forms/select — Validation: nullable — Config: ->label('Akun warga'), ->options(aturan §4.2 peran Warga), ->searchable(),
           ->helperText('Akun yang bisa melihat iuran & riwayat pengangkutan pelanggan ini.')
    Field: catatan_pengangkutan — Filament\Forms\Components\Textarea — https://filamentphp.com/docs/5.x/forms/textarea — Validation: nullable, max:1000 — Config: ->label('Catatan untuk petugas'), ->rows(2), ->columnSpanFull()

Infolist (untuk ViewAction petugas — Columns 2):
  Entry: nama, alamat (columnSpanFull), rt, rw, patokan, no_hp, catatan_pengangkutan —
         Filament\Infolists\Components\TextEntry — https://filamentphp.com/docs/5.x/infolists/text-entry — ->placeholder('—')

Table — tambah:
  Column: petugas.name — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->label('Petugas'), ->placeholder('— belum ada —'), ->visible(admin)
  Column: alamat — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->limit(40), ->tooltip(alamat), ->toggleable()
  Column: patokan — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->toggleable(isToggledHiddenByDefault: true)
  Kolom iuran_bulanan & tarifLayanan.nama — ->visible(fn () => auth()->user()->isAdminKsm())  (petugas tak melihat data keuangan)
  Filter: petugas_id — Filament\Tables\Filters\SelectFilter — https://filamentphp.com/docs/5.x/tables/filters/select — ->options(petugas KSM ini) — ->visible(admin)
  Filter: tanpa_petugas — Filament\Tables\Filters\Filter — https://filamentphp.com/docs/5.x/tables/filters/custom —
          ->label('Tanpa petugas aktif'), ->query(fn (Builder $q) => $q->tanpaPetugasAktif()), ->toggle()
  Actions:
    Action: ViewAction — Filament\Actions\ViewAction — row — Visibility: petugas (admin memakai Edit) — Authorization: PelangganPolicy::view
    Action: bukaPeta
      Component: Filament\Actions\Action — Docs: https://filamentphp.com/docs/5.x/actions/overview — Location: table row
      Visibility: $record->mapsUrl() !== null
      Authorization: PelangganPolicy::view
      Behavior: ->url(fn (Pelanggan $r) => $r->mapsUrl(), shouldOpenInNewTab: true), ikon Heroicon::OutlinedMapPin
    Action: tetapkanPetugas (BULK)
      Component: Filament\Actions\BulkAction — Docs: https://filamentphp.com/docs/5.x/tables/actions#bulk-actions
      Location: toolbar (BulkActionGroup)
      Visibility & Authorization: ->authorize(fn (): bool => auth()->user()->isAdminKsm())
      Behavior: 1) modal schema: Select petugas_id (wajib; opsi = petugas AKTIF KSM ini; tanpa nilai lama)
                2) $records->each->update(['petugas_id' => $data['petugas_id']])  (records berasal dari query tabel ber-scope tenant)
                3) notifikasi sukses "N pelanggan ditugaskan ke {nama}"
getEloquentQuery(): parent + bila user->isPetugas() → where('petugas_id', user.id); bila user->isWarga() → whereRaw('1 = 0') (pertahanan berlapis)
```

### 4.4 Authorization (Fase 2)
```
Policy: App\Policies\PelangganPolicy
  viewAny: isAdminKsm() OR isPetugas()
  view:    isAdminKsm() OR (isPetugas() AND pelanggan.petugas_id === user.id)
  create:  isAdminKsm()
  update:  isAdminKsm()
  delete, deleteAny: false (tetap seperti sekarang: pelanggan dinonaktifkan, bukan dihapus)
Field visibility: iuran_bulanan, tarif_layanan_id, user_id, catatan (internal) — hanya admin
```

---

## 5. Fase 3 — Pengangkutan harian & dasbor per peran

### 5.1 Models
```
Enum: App\Enums\StatusPengangkutan (string-backed)
  Implements: HasLabel, HasColor, HasIcon
  Cases:
    - Diangkut = 'diangkut'                     label "Diangkut"            color "success" icon Heroicon::OutlinedCheckCircle
    - TidakAdaSampah = 'tidak_ada_sampah'       label "Tidak ada sampah"    color "gray"    icon Heroicon::OutlinedMinusCircle
    - TidakBisaDiakses = 'tidak_bisa_diakses'   label "Tidak bisa diakses"  color "danger"  icon Heroicon::OutlinedExclamationTriangle
  Method: butuhBerat(): bool — true hanya Diangkut

Model: App\Models\Pengangkutan
  Table: pengangkutan   (#[Table('pengangkutan')])
  Attributes:
    - id: bigint, primary
    - ksm_id: bigint, foreign(ksm.id), required, cascadeOnDelete
    - petugas_id: bigint, foreign(users.id), required, restrictOnDelete
    - tanggal: date, required
    - catatan: text, nullable
    - created_at, updated_at: timestamp
    - unique(['petugas_id','tanggal']); index(['ksm_id','tanggal'])
  Fillable: tanggal, catatan   (ksm_id: asosiasi tenant; petugas_id: diisi server, lihat §5.3)
  Casts: tanggal → 'date'
  Relationships: belongsTo Ksm via ksm_id; belongsTo User via petugas_id (petugas()); hasMany PengangkutanRincian via pengangkutan_id (rincian())
  Methods: bisaDiubahPetugas() (§2.4)

Model: App\Models\PengangkutanRincian
  Table: pengangkutan_rincian
  Attributes:
    - id: bigint, primary
    - pengangkutan_id: bigint, foreign(pengangkutan.id), required, cascadeOnDelete
    - pelanggan_id: bigint, foreign(pelanggan.id), required, cascadeOnDelete
    - status: string(30), default:'diangkut'
    - perkiraan_kg: decimal(6,1), nullable
    - catatan: string(255), nullable
    - created_at, updated_at: timestamp
    - unique(['pengangkutan_id','pelanggan_id'])
  Fillable: pelanggan_id, status, perkiraan_kg, catatan
  Casts: status → StatusPengangkutan::class, perkiraan_kg → 'decimal:1'
  Relationships: belongsTo Pengangkutan; belongsTo Pelanggan
  Hook saving: aturan §2.5
  (Tidak punya resource → TIDAK di-scope tenant otomatis; semua query di luar repeater wajib lewat
   Pengangkutan ber-scope atau whereHas pelanggan ber-ksm.)

Ksm: + hasMany Pengangkutan (pengangkutan())
```

### 5.2 Resource: `App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource` (BARU)
```
Command: php artisan make:filament-resource Pengangkutan --panel=ksm --record-title-attribute=tanggal --no-interaction
Location: App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource (+ Pages ListPengangkutans, CreatePengangkutan, EditPengangkutan)
Docs: https://filamentphp.com/docs/5.x/resources/overview
$tenantRelationshipName = 'pengangkutan'
Navigation: Group 'Data Input', Label 'Pengangkutan', Icon Heroicon::OutlinedTruck, Sort 20
Imports: Filament\Schemas\Components\Utilities\Get, Filament\Schemas\Components\Utilities\Set

Form (Columns 1):
  Section 'Hari & petugas' — ->columns(2)
    Field: tanggal
      Component: Filament\Forms\Components\DatePicker
      Docs: https://filamentphp.com/docs/5.x/forms/date-time-picker
      Validation: required, date, before_or_equal:Hari::ini();
                  petugas: after_or_equal:Hari::ini()->subDay() (server-side, bukan hanya UI);
                  closure unik: tak boleh ada Pengangkutan lain dengan petugas_id efektif + tanggal sama (abaikan record saat edit)
                  → 'Catatan petugas ini untuk tanggal tersebut sudah ada — buka dan ubah yang ada.'
      Config: ->native(false), ->displayFormat('d F Y'), ->default(Hari::ini()), ->maxDate(Hari::ini()),
              ->minDate(fn () => auth()->user()->isPetugas() ? Hari::ini()->subDay() : null),
              ->disabled(fn (string $operation) => $operation === 'edit' && auth()->user()->isPetugas())
    Field: petugas_id
      Component: Filament\Forms\Components\Select
      Docs: https://filamentphp.com/docs/5.x/forms/select
      Validation: required (in-rule dari opsi)
      Config: ->visible(fn () => auth()->user()->isAdminKsm()), ->options(aturan §4.2 peran Petugas), ->searchable(), ->live()
      Reactive (create saja): saat berubah → Set 'rincian' = item default untuk petugas terpilih (lihat Field rincian)
    Field: catatan — Filament\Forms\Components\Textarea — Validation: nullable, max:1000 — Config: ->rows(2), ->columnSpanFull()

  Field: rincian
    Component: Filament\Forms\Components\Repeater
    Docs: https://filamentphp.com/docs/5.x/forms/repeater
    Validation: minItems 1
    Config: ->relationship('rincian'), ->label('Rincian per rumah'), ->columns(4), ->reorderable(false),
            ->addActionLabel('Tambah rumah'), ->deletable(true),
            ->itemLabel(fn (array $state): ?string => nama pelanggan dari $state['pelanggan_id']),
            ->default(fn (): array => item default)   — create saja
    Item default: untuk petugas login (atau petugas_id terpilih admin): pelanggan KSM ini, aktif, petugas_id = petugas itu,
                  urut nomor_urut lalu nama → [['pelanggan_id' => id, 'status' => 'diangkut', 'perkiraan_kg' => null], ...]
    Schema:
      Field: pelanggan_id
        Component: Filament\Forms\Components\Select
        Validation: required (in-rule dari opsi)
        Config: ->options(petugas: pelanggan aktif dengan petugas_id = user.id; admin: semua pelanggan aktif KSM ini;
                          keduanya + nilai tersimpan pada edit), ->searchable(), ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                ->columnSpan(2)
      Field: status
        Component: Filament\Forms\Components\ToggleButtons
        Docs: https://filamentphp.com/docs/5.x/forms/toggle-buttons
        Validation: required, enum StatusPengangkutan
        Config: ->options(StatusPengangkutan::class), ->inline(), ->default(StatusPengangkutan::Diangkut), ->live(), ->columnSpan(2)
      Field: perkiraan_kg
        Component: Filament\Forms\Components\TextInput
        Validation: wajib bila status diangkut; numeric, min:0.1, max:500
        Config: ->numeric(), ->step(0.1), ->suffix('kg'),
                ->required(fn (Get $get): bool => StatusPengangkutan::tryFrom((string) $get('status'))?->butuhBerat() ?? false),
                ->visible(fn (Get $get): bool => sama)
        Reactive: tampil hanya saat status Diangkut; nilai dikosongkan di model (§2.5)
      Field: catatan — Filament\Forms\Components\TextInput — https://filamentphp.com/docs/5.x/forms/text-input — Validation: nullable, max:255 — Config: ->columnSpan(2)

Pages:
  CreatePengangkutan::mutateFormDataBeforeCreate(): bila ! isAdminKsm() → $data['petugas_id'] = auth()->id() (abaikan input klien)
  EditPengangkutan: petugas tidak punya field petugas_id → tak berubah; tanggal disabled untuk petugas (tak di-dehydrate)

Table:
  modifyQueryUsing: ->with('petugas')->withCount(['rincian as rumah_diangkut_count' => fn ($q) => $q->where('status','diangkut')])
                    ->withSum('rincian as total_kg', 'perkiraan_kg')
  Column: tanggal — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->date('d M Y'), ->sortable()   (defaultSort tanggal desc)
  Column: petugas.name — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->label('Petugas'), ->searchable(), ->visible(admin)
  Column: rumah_diangkut_count — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->label('Rumah diangkut'), ->numeric()
  Column: total_kg — Filament\Tables\Columns\TextColumn — https://filamentphp.com/docs/5.x/tables/columns/text — ->label('Perkiraan'), ->numeric(decimalPlaces: 1), ->suffix(' kg'),
          ->summarize(Filament\Tables\Columns\Summarizers\Summarizer::make()->label('Total')
             ->using(fn (Illuminate\Database\Query\Builder $q): float => (float) PengangkutanRincian::query()
                 ->whereIn('pengangkutan_id', (clone $q)->pluck('id'))->sum('perkiraan_kg')))
          Docs: https://filamentphp.com/docs/5.x/tables/summaries#custom-summaries
  Filter: petugas_id — Filament\Tables\Filters\SelectFilter — https://filamentphp.com/docs/5.x/tables/filters/select — ->options(petugas KSM ini) — ->visible(admin)
  Filter: periode — Filament\Tables\Filters\Filter — https://filamentphp.com/docs/5.x/tables/filters/custom —
          ->schema([Filament\Forms\Components\DatePicker::make('dari'), DatePicker::make('sampai')]),
          ->query(fn (Builder $q, array $data) => $q->when($data['dari'], fn ($q, $d) => $q->whereDate('tanggal', '>=', $d))
                                                    ->when($data['sampai'], fn ($q, $d) => $q->whereDate('tanggal', '<=', $d)))
  Actions (row): Filament\Actions\EditAction, Filament\Actions\DeleteAction — https://filamentphp.com/docs/5.x/actions/overview — tampil/izin dari PengangkutanPolicy

getEloquentQuery(): parent + bila isPetugas() → where('petugas_id', user.id); bila isWarga() → whereRaw('1 = 0')
```

### 5.3 Authorization (Fase 3)
```
Policy: App\Policies\PengangkutanPolicy
  viewAny: isAdminKsm() OR isPetugas()
  view:    isAdminKsm() OR (isPetugas() AND record.petugas_id === user.id)
  create:  isAdminKsm() OR isPetugas()
  update:  isAdminKsm() OR (isPetugas() AND record.petugas_id === user.id AND record->bisaDiubahPetugas())
  delete:  sama dengan update
  deleteAny, forceDelete, restore: false
```

### 5.4 Dasbor per peran (`Filament\Pages\Dashboard` tetap; widget difilter `static canView()`)
```
ADMIN (canView: isAdminKsm()):
  IuranStatsOverview (lama, sort 1), IuranPerBulanChart (lama, sort 2)
  Widget: OperasionalStatsOverview — Filament\Widgets\StatsOverviewWidget — sort 3
    Stat 'Diangkut hari ini': jumlah rincian status diangkut pada Hari::ini() / pelanggan aktif — color primary — icon Heroicon::OutlinedTruck
    Stat 'Perkiraan bulan ini': sum perkiraan_kg bulan berjalan (Hari) — format "1.234,5 kg" — color success
    Stat 'Tanpa petugas aktif': Pelanggan::tanpaPetugasAktif()->where('aktif',true)->count() — color danger — icon Heroicon::OutlinedExclamationTriangle
  Widget: BeratPerHariChart — Filament\Widgets\ChartWidget — type bar — sort 4 — columnSpan full
    Data: sum perkiraan_kg per tanggal, 30 hari terakhir s.d. Hari::ini(), hari tanpa catatan = 0
    Docs: https://filamentphp.com/docs/5.x/widgets/charts

PETUGAS (canView: isPetugas()):
  Widget: TugasPetugasStatsOverview — sort 1
    Stat 'Rumah tugas saya': pelanggan aktif dengan petugas_id = user.id
    Stat 'Diangkut hari ini': rincian diangkut milik pengangkutan user pada Hari::ini()
    Stat 'Perkiraan bulan ini': sum kg pengangkutan user bulan berjalan
  Widget: DaftarTugasHariIni — Filament\Widgets\TableWidget — sort 2 — columnSpan full
    Docs: https://filamentphp.com/docs/5.x/widgets/overview#table-widgets
    Query: Pelanggan::query()->whereBelongsTo(Filament::getTenant())->where('petugas_id', auth()->id())->where('aktif', true)
    Columns (semua Filament\Tables\Columns\TextColumn, https://filamentphp.com/docs/5.x/tables/columns/text): nomor_urut, nama ->searchable(),
             alamat ->limit(40), rt/rw, patokan, no_hp, status hari ini ->badge() (state dari map rincian Hari::ini(), placeholder 'Belum dicatat')
    Record action: bukaPeta (sama dengan §4.3)
    Header action: 'Catat pengangkutan hari ini' → url ke Edit catatan hari ini bila ada, selain itu ke Create
    Table config: ->paginated([25, 50, 'all'])

WARGA (canView: isWarga()):
  Widget: WargaStatsOverview — sort 1 — satu Stat per pelanggan milik user (pelanggan.user_id = user.id, KSM ini):
    label: nama pelanggan; value: StatusIuran bulan berjalan (StatusIuran::tentukan dgn pembayaran periode Hari::ini() awal bulan);
    description: 'Petugas: {nama} ({no_hp})' atau 'Belum ada petugas'; color: dari StatusIuran
  Widget: RiwayatIuranSaya — TableWidget — sort 2
    Query: PembayaranIuran::query()->whereBelongsTo(Filament::getTenant())->whereHas('pelanggan', fn ($q) => $q->where('user_id', auth()->id()))
    Columns (Filament\Tables\Columns\TextColumn): periode ->date('F Y'), pelanggan.nama, nominal ->money('IDR', locale: 'id', decimalPlaces: 0), dibayar_pada ->date('d M Y')->placeholder('—') — defaultSort periode desc — paginated 10
  Widget: RiwayatPengangkutanSaya — TableWidget — sort 3
    Query: PengangkutanRincian::query()->whereHas('pelanggan', fn ($q) => $q->where('user_id', auth()->id())->whereBelongsTo(Filament::getTenant()))
           ->with(['pengangkutan.petugas','pelanggan'])
    Columns (Filament\Tables\Columns\TextColumn): pengangkutan.tanggal ->date('d M Y'), pelanggan.nama, status ->badge(),
             perkiraan_kg ->numeric(decimalPlaces: 1)->suffix(' kg')->placeholder('—'), pengangkutan.petugas.name ->label('Petugas')
    defaultSort tanggal desc (join/orderBy subquery) — paginated 10
```
Super admin di panel desa = admin (melihat widget admin).

---

## 6. Tests (PHPUnit + `Livewire\Livewire::test`, gaya proyek; bukan Pest)

Prasyarat terverifikasi: `Filament\Actions\Testing\TestAction` (ada), `Filament\Forms\Components\Repeater::fake()`
(dari trait `CanGenerateUuids`), `Livewire::withHeaders(['Host' => 'makmurjaya.ksmdesa.test'])` mengirim header sebagai
server var (`RequestBroker::addHeaders`) → `request()->getHost()` = subdomain. **Verifikasi di test pertama** bahwa host itu
bertahan pada request update Livewire berikutnya; kalau tidak, uji login lewat jalur yang setara dan catat.
Perintah fokus: `php artisan test --compact --filter=<NamaTest>`; semua: `php artisan test --compact`, lalu ulang dengan
`APP_DOMAIN=ksmdesa.test APP_URL=http://ksmdesa.test php artisan route:cache` + `route:clear`.
Data KSM lain di test **harus** dibuat dengan `Filament::setTenant(null, isQuiet: true)` (observer `creating` Filament
memindahkan record baru ke tenant aktif).

```
Fase 1 — LoginSubdomainTest:
  - petugas no_hp tersimpan '081234567890' login dengan input '+62 812-3456-7890' di makmurjaya → berhasil (normalisasi roundtrip)
  - nomor HP sama terdaftar di KSM lain dgn sandi berbeda: sandi makmurjaya berhasil di makmurjaya; sandi KSM lain gagal di makmurjaya
  - pengguna KSM A tidak bisa login di subdomain KSM B (pesan galat di data.login, tidak terautentikasi)
  - akun nonaktif: sandi benar → gagal dengan pesan generik yang sama
  - super admin login dengan email di makmurjaya → berhasil, diarahkan ke makmurjaya (buat KSM 'aaa…' lebih dulu agar
    "KSM pertama abjad" ≠ makmurjaya — fixture asimetris)
  - pengguna desa yang punya email TIDAK bisa login dengan email (email hanya untuk super admin)
  - halaman login makmurjaya menampilkan <img> logo bila logo di-set; KSM tanpa logo menampilkan nama
UserManagementTest (panel desa):
  - admin membuat petugas → ksm_id = tenant, no_hp ternormalisasi tersimpan
  - validasi (dataset): name required/max255; no_hp required, format salah, duplikat dalam KSM (input '+62…' vs tersimpan '08…')
    → error; no_hp sama di KSM lain → lolos; email format; password required saat create, min 8; kosong saat edit → sandi tidak berubah
  - admin tidak bisa mengubah peran/aktif dirinya: field disabled, dan data tersimpan tetap
  - petugas & warga: halaman Pengguna 403, menu tidak tampil
  - aksi alihAktif menonaktifkan; pengguna nonaktif tak bisa login; tidak ada aksi hapus
  - daftar pengguna tidak memuat pengguna KSM lain maupun super admin
MasterUsersRelationManagerTest:
  - super admin membuat pengguna untuk KSM X lewat RM → ksm_id = X; tidak ada Attach/Detach/Delete
ProfilKsmTest:
  - admin desa mengganti nama & logo (Storage::fake('public')) → berkas lama terhapus; petugas/warga 403
  - menu tenant tersembunyi untuk pengguna desa (Filament::getCurrentOrDefaultPanel()->hasTenantMenu() false), tampil untuk super admin
Migrasi: ksm_user admin non-super-admin → users.ksm_id + peran admin; super admin tetap ksm_id null

Fase 2 — PelangganDetailTest:
  - dua petugas, fixture asimetris: petugas A hanya melihat pelanggan tugas A di daftar; view pelanggan B → 403
  - petugas tidak melihat kolom iuran/tarif; tidak ada EditAction; callAction edit → ditolak
  - bulk 'tetapkanPetugas': hanya record terpilih berubah; opsi tidak memuat petugas nonaktif / KSM lain; ID petugas KSM lain → error
  - simpan pelanggan tanpa mengubah petugas yang kini nonaktif → sukses (aturan §4.2)
  - user_id hanya menerima warga KSM ini
  - mapsUrl dari koordinat & dari alamat; null bila keduanya kosong

Fase 3 — PengangkutanTest:
  - create oleh petugas: repeater terisi tepat pelanggan aktif tugasnya (bukan milik petugas lain, bukan nonaktif) — Repeater::fake()
  - petugas memasukkan pelanggan petugas lain (ID dimanipulasi) → error validasi pada item
  - petugas_id dari klien diabaikan → tersimpan = petugas login
  - (petugas, tanggal) duplikat → error tanggal
  - batas tanggal petugas: kemarin lolos, 2 hari lalu ditolak, besok ditolak; admin 2 hari lalu lolos
  - jendela ubah: travelTo WITA — catatan kemarin bisa diedit; catatan 2 hari lalu: EditAction tersembunyi DAN akses URL edit 403
  - status diangkut tanpa kg → error; kg 0 → error; 500.1 → error; ubah status ke tidak_ada_sampah pada edit → kg tersimpan null (roundtrip)
  - petugas tidak melihat pengangkutan petugas lain (daftar + URL langsung)
  - summarizer total kg = jumlah rincian difilter
DasborPeranTest:
  - widget yang tampil per peran (admin/petugas/warga) via canView
  - warga hanya melihat iuran & pengangkutan pelanggan miliknya (fixture: pelanggan warga lain di KSM sama + KSM lain)
  - warga melihat nama + nomor HP petugas; warga → 403 untuk Pelanggan/Pengangkutan/Rekap/Pengguna
```

---

## 7. Setelah tiap fase
1. `vendor/bin/pint --dirty --format agent`, test penuh (+ `route:cache`).
2. Skill `filament-security-audit` (kategori A–E) atas fase itu; temuan diperbaiki sebelum commit.
3. Commit, push, `sudo -u ksmdesa -i ~/deploy/deploy.sh`, cek URL produksi (login HP di makmurjaya, 404 di apex/subdomain asing).
4. Fase 1 di produksi: belum ada pengguna → pemilik menjalankan `php artisan app:super-admin`, lalu membuat admin desa makmurjaya.

## 8. Di luar cakupan (tidak dikerjakan tanpa permintaan)
- Petugas menagih/mencatat iuran; jadwal pengangkutan terencana & "realisasi jadwal"; peta interaktif; foto bukti;
  notifikasi WhatsApp; reset kata sandi via SMS/WA (email belum aktif).
