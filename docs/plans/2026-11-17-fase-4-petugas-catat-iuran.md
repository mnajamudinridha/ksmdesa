# Blueprint Fase 4: Petugas mencatat iuran & setoran ke admin

Status: **RENCANA — menunggu persetujuan pemilik.** Lanjutan dari
`docs/plans/2026-11-17-peran-pengguna-dan-pengangkutan.md` (Fase 1–3); semua aturan kanonik di sana
(`App\Support\Hari`, `NomorHp`, tenancy satu-DB, `diKsmLain`, sabotase-test) tetap berlaku.
Disusun dengan skill `planning-filament` (Filament Blueprint v2.4).
Versi terpasang: Laravel 13.42 · Filament 5.11.4 · Livewire 4.4.8 · PHPUnit 12 (bukan Pest).
Sumber verifikasi: **Boost `search-docs` tidak tersedia di sesi ini (MCP terputus)** → API diverifikasi
terhadap kode sumber terpasang di `vendor/filament` (baris disebut di tiap bagian) dan dokumentasi v5
(tautan). Tidak ada asumsi yang belum diverifikasi yang mengubah rencana; yang masih perlu dicek saat
implementasi ditandai **[cek]**.

## 0. Keputusan pemilik (2026-11-17)

| Topik | Keputusan |
|---|---|
| Kapan dihitung lunas | **Langsung saat dicatat petugas.** Rekap, dasbor, dan riwayat warga memakai baris `pembayaran_iuran` apa adanya. Status "belum disetor" hanya untuk kas. |
| Setoran | **Setoran per petugas**: admin membuat catatan Setoran — pilih petugas, centang pembayaran yang diserahkan, tanggal, penerima (= admin yang mencatat), total. Ada riwayat setoran. |
| Isi catatan petugas | **Beberapa bulan sekaligus**, nominal per bulan default = iuran bulanan pelanggan, **boleh diubah** (bayar kurang/lebih). |
| Tempat catat | **Tombol "Terima iuran" per rumah** di widget *Tugas hari ini*, plus menu **"Iuran diterima"** untuk melihat & mengoreksi catatan sendiri. |

Keputusan turunan yang diusulkan rencana ini (disetujui bersama rencana; ubah bila tidak sesuai):

| Topik | Usulan |
|---|---|
| Koreksi oleh petugas | Petugas boleh mengubah/menghapus catatannya **selama belum masuk setoran**. Setelah disetor: terkunci untuk petugas. |
| Koreksi oleh admin | Admin boleh mengubah pembayaran apa pun; **menghapus** hanya yang belum masuk setoran. Total setoran **dihitung langsung** dari pembayarannya (tidak disimpan), jadi koreksi admin tidak membuat total basi. |
| Batal setoran | Admin boleh **menghapus setoran** (dengan konfirmasi): pembayarannya kembali "belum disetor"; tidak ada pembayaran yang hilang. Setoran tidak bisa diedit — batalkan lalu buat ulang. |
| Rentang bulan | Petugas memilih dari **6 bulan ke belakang s.d. 2 bulan ke depan** (hari desa), hanya bulan yang belum tercatat untuk pelanggan itu. |
| Tanggal terima | Petugas: hari ini atau kemarin (sama dengan jendela pengangkutan). Admin: bebas (form admin yang ada). |
| Catatan admin langsung | Pembayaran yang dicatat admin lewat menu Pembayaran Iuran (dan 167 baris impor lama) = "diterima langsung", **tidak pernah perlu disetor**. |

Di luar cakupan: kuitansi/cetak bukti, notifikasi WA, selisih uang fisik vs catatan, petugas mencatat
untuk rumah yang bukan tugasnya.

## 1. Commands

```bash
php artisan make:migration add_petugas_dan_setoran_to_pembayaran_iuran_table --table=pembayaran_iuran --no-interaction
php artisan make:model Setoran --migration --factory --no-interaction
php artisan make:policy SetoranPolicy --model=Setoran --no-interaction
php artisan make:filament-resource Setoran --panel=ksm --view --no-interaction
php artisan make:test --phpunit PetugasIuranTest --no-interaction
php artisan make:test --phpunit SetoranTest --no-interaction
```

Catatan: `make:model Setoran --migration` menghasilkan tabel `setorans` → ganti ke **`setoran`** dan beri
`#[Table('setoran')]` (konvensi proyek). Resource: pindahkan ke pola file-tunggal proyek
(`app/Filament/Ksm/Resources/Setorans/SetoranResource.php` + `Pages/`), hapus `Schemas/`/`Tables/` bila
generator membuatnya. **Urutan migrasi:** tabel `setoran` harus dibuat **sebelum** migrasi kolom
`pembayaran_iuran.setoran_id` (FK) — beri timestamp migrasi `setoran` lebih awal.

## 2. Aturan kanonik bersama

**2.1 Status setor** (satu aturan, dipakai tabel, filter, kebijakan, widget):

| `petugas_id` | `setoran_id` | Status | Badge |
|---|---|---|---|
| null | — | Diterima langsung (admin/impor) | `gray` "Langsung" |
| terisi | null | **Belum disetor** — uang dipegang petugas | `warning` |
| terisi | terisi | Disetor (tanggal setoran) | `success` |

Scope model `PembayaranIuran::scopeBelumDisetor(Builder $q)`: `whereNotNull('petugas_id')->whereNull('setoran_id')`.

**2.2 Bulan yang boleh dicatat** — `App\Support\BulanIuran::pilihan(Pelanggan $pelanggan): array<string, string>`
(key `Y-m-d` hari pertama bulan, label `translatedFormat('F Y')`), urut lama → baru:
- rentang: `Hari::ini()->startOfMonth()->subMonths(6)` s.d. `->addMonths(2)`;
- kecuali bulan yang sudah punya `pembayaran_iuran` untuk pelanggan itu (bandingkan `periode` sebagai
  `Y-m-d` string — aturan "tanggal = string" dari `Hari`).
Dipakai sebagai **options** `CheckboxList` → Filament menerapkan rule `in` ke setiap nilai
(`CheckboxList::hasInValidationOnMultipleValues()` = true, `vendor/filament/forms/src/Components/CheckboxList.php:521-534`),
jadi bulan yang sudah lunas atau di luar rentang ditolak server-side. Index unik
`(pelanggan_id, periode)` yang sudah ada adalah penjaga terakhir (balapan dua petugas).

**2.3 Hak petugas atas sebuah pembayaran** (kebijakan, aksi tabel, dan rute edit memakai ini):
`$user->isPetugas() && $p->petugas_id === $user->getKey() && $p->setoran_id === null`.

**2.4 Petugas boleh menerima iuran dari pelanggan** — `PelangganPolicy::terimaIuran(User, Pelanggan)`:
`$user->isPetugas() && $pelanggan->aktif && $pelanggan->petugas_id === $user->getKey()`.

**Konsistensi (diperiksa):** "lunas" (StatusIuran/RingkasanIuran) tidak membaca `petugas_id`/`setoran_id`
sama sekali → catatan petugas langsung lunas, setoran tidak mengubah rekap (sesuai keputusan). Bulan yang
sudah dicatat siapa pun hilang dari pilihan petugas berikutnya → tidak ada dua catatan untuk satu bulan.
Pembayaran yang masuk setoran terkunci bagi petugas di kebijakan, sehingga total setoran (dihitung
langsung) hanya bisa berubah lewat koreksi admin — disengaja dan terlihat di halaman setoran.

## 3. Models

### 3.1 Migrasi `setoran` (baru)
```
id
ksm_id            foreignId → ksm, cascadeOnDelete
petugas_id        foreignId → users, cascadeOnDelete   // sama alasan dg pengangkutan (hapus KSM meng-cascade users)
diterima_oleh_id  foreignId → users, nullable, nullOnDelete  // admin/super admin penerima
tanggal           date                                  // hari desa (Hari::tanggal())
catatan           text nullable
timestamps
index (ksm_id, tanggal)
```

### 3.2 Migrasi kolom `pembayaran_iuran` (ubah)
```
petugas_id  foreignId → users, nullable, nullOnDelete, after pelanggan_id   // yang menerima uang; null = langsung
setoran_id  foreignId → setoran, nullable, nullOnDelete, after petugas_id
index (petugas_id, setoran_id)
```
Data lama (167 baris produksi): kedua kolom null → "Langsung". Tidak ada backfill.

### 3.3 `App\Models\Setoran`
- `#[Table('setoran')]`, `#[Fillable(['petugas_id', 'tanggal', 'catatan'])]` — `ksm_id` dari asosiasi tenant Filament,
  `diterima_oleh_id` di-`forceFill` saat create (bukan dari input).
- casts: `tanggal => 'date'`.
- relasi: `ksm()` BelongsTo; `petugas()` BelongsTo User `petugas_id`; `penerima()` BelongsTo User `diterima_oleh_id`;
  `pembayaranIuran()` HasMany.
- `Ksm::setoran()` HasMany (tenant relationship); `User::setoran()` HasMany via `petugas_id`.

### 3.4 `App\Models\PembayaranIuran` (ubah)
- **Tidak** menambah `petugas_id`/`setoran_id` ke `#[Fillable]` — diisi `forceFill` oleh aksi Terima iuran dan
  oleh pembuatan setoran (query `update`).
- relasi baru: `petugas()` BelongsTo User `petugas_id`; `setoran()` BelongsTo Setoran.
- scope `belumDisetor` (§2.1); helper `statusSetor(): string` ('langsung'|'belum'|'disetor') untuk badge.

## 4. Fitur

### 4.1 Aksi "Terima iuran" di widget *Tugas hari ini* (petugas)
Lokasi: `app/Filament/Ksm/Widgets/DaftarTugasHariIni.php`, `recordActions`.
- **Action**: `Filament\Actions\Action::make('terimaIuran')` — Docs: https://filamentphp.com/docs/5.x/actions/modals
  - Label "Terima iuran", icon `Heroicon::OutlinedBanknotes`, color `success`, `->modalSubmitActionLabel('Simpan')`.
  - Visibility/Authorization: `->authorize(fn (Pelanggan $record): bool => auth()->user()->can('terimaIuran', $record))`
    (§2.4) — ditolak = tersembunyi & 403 saat dipanggil.
  - Tata letak tombol di widget: `Peta` tetap tombol langsung; `terimaIuran` juga tombol langsung (aksi utama
    petugas di lapangan; widget bukan resource, jadi tidak masuk ActionGroup — sesuai keputusan sebelumnya).
- **Form** (`->schema([...])`), Imports: `Filament\Schemas\Components\Utilities\Get`:
  - `Filament\Forms\Components\CheckboxList::make('bulan')` — Docs: https://filamentphp.com/docs/5.x/forms/checkbox-list
    Validation: required, `->minItems(1)`; options `fn (Pelanggan $record) => BulanIuran::pilihan($record)` (in-rule otomatis).
    Config: `->default(fn (Pelanggan $record) => array_key_exists($ini = Hari::ini()->startOfMonth()->toDateString(), BulanIuran::pilihan($record)) ? [$ini] : [])`,
    `->columns(3)`, `->bulkToggleable()`, `->live()`.
  - `Filament\Forms\Components\TextInput::make('nominal')` — Docs: https://filamentphp.com/docs/5.x/forms/text-input
    Label "Nominal per bulan"; Validation: required, integer, min:1, max:10000000;
    Config: `->numeric()->prefix('Rp')->default(fn (Pelanggan $record) => $record->iuran_bulanan)->live(onBlur: true)`.
  - `Filament\Forms\Components\DatePicker::make('dibayar_pada')` — Docs: https://filamentphp.com/docs/5.x/forms/date-time-picker
    Label "Tanggal diterima"; Validation: required; Config: `->native(false)->default(fn () => Hari::tanggal())->maxDate(fn () => Hari::tanggal())->minDate(fn () => Hari::tanggal(1))`.
  - `Filament\Forms\Components\TextInput::make('catatan')` — maxLength 255.
  - `Filament\Infolists\Components\TextEntry::make('total')` — Docs: https://filamentphp.com/docs/5.x/infolists/text-entry
    Label "Total diterima"; Config: `->state(fn (Get $get) => RingkasanIuran::rupiah(count($get('bulan') ?? []) * (int) $get('nominal')))`. **[cek]** entri infolist di skema form aksi tabel (Filament 5 schemas mendukung campuran; bila tidak, pakai `Filament\Schemas\Components\Text`).
- **Behavior** (`->action(function (array $data, Pelanggan $record): void {...})`):
  1. `DB::transaction`: untuk tiap `periode` di `$data['bulan']` (sudah lolos in-rule): buat `PembayaranIuran`
     `fill(['pelanggan_id', 'periode', 'nominal', 'dibayar_pada', 'catatan'])` + `forceFill(['petugas_id' => auth()->id()])` → `save()`
     (hook `saving` mengisi `ksm_id` dari pelanggan & menormalkan periode).
  2. Tangkap `UniqueConstraintViolationException` (balapan) → `throw ValidationException::withMessages(['mountedActions.0.data.bulan' => 'Bulan ini baru saja dicatat orang lain — muat ulang.'])` **[cek]** kunci error aksi; alternatif: `Notification` danger + `$action->halt()`.
  3. `Notification::make()->success()->title("{n} bulan dicatat — total Rp…")`.
- Kolom tambahan di widget: `TextColumn::make('status_iuran_bulan_ini')` "Iuran bulan ini" — `addSelect` subquery `sum(nominal)` bulan berjalan (hari desa);
  state → `StatusIuran::tentukan($record->aktif, $record->iuran_bulanan, $dibayar)`; `->badge()`, label & color dari enum.

### 4.2 Menu Pembayaran Iuran ↔ "Iuran diterima" (ubah `PembayaranIuranResource`)
Satu resource, berbeda per peran (pola proyek, spt Pelanggan/Pengangkutan):
- `getEloquentQuery()`: admin → semua (+ `with(['pelanggan', 'petugas', 'setoran'])`); petugas → `where('petugas_id', me)`; lainnya → `whereRaw('1 = 0')`.
- `getNavigationLabel()`: admin "Pembayaran Iuran", petugas "Iuran diterima". Grup tetap "Data Input".
- `canCreate` mengikuti kebijakan `create` = admin saja (petugas mencatat lewat widget).
- **Form edit untuk petugas**: `pelanggan_id` `->disabled(fn () => ! auth()->user()->isAdminKsm())` (tidak dipindah pelanggan);
  `periode` aturan unik yang sudah ada tetap; untuk petugas `periode` options/valid harus di rentang §2.2 **[cek]**
  (DatePicker bebas → tambahkan `->rule` jendela §2.2 bila petugas); `dibayar_pada` min kemarin untuk petugas.
- **Create oleh admin** (ubah `ManagePembayaranIurans`): tidak berubah — `petugas_id` tetap null ("Langsung").
- **Kolom baru**:
  - `TextColumn::make('status_setor')` "Setoran" — `->state(fn (PembayaranIuran $r) => $r->statusSetor())`, `->badge()`,
    label: Langsung / Belum disetor / "Disetor {tanggal}", warna §2.1.
  - `TextColumn::make('petugas.name')` "Diterima oleh" — admin saja, placeholder "Pengurus".
- **Filter**: `Filament\Tables\Filters\SelectFilter::make('status_setor')` opsi langsung/belum/disetor (query per §2.1);
  `SelectFilter::make('petugas_id')` (admin) opsi `PilihanPengguna::petugas()`.
- Summarizer `Sum` nominal yang sudah ada tetap (petugas: total sesuai filter, mis. "Belum disetor").
- Aksi baris (ActionGroup yang sudah ada): `EditAction`, `DeleteAction` — tampil sesuai kebijakan §5.

### 4.3 Resource Setoran (baru, admin)
- **Resource**: `App\Filament\Ksm\Resources\Setorans\SetoranResource` — Docs: https://filamentphp.com/docs/5.x/resources/overview
  `$tenantRelationshipName = 'setoran'`, grup "Data Input", sort 30, icon `Heroicon::OutlinedInboxArrowDown`,
  label "setoran petugas". Halaman: List, Create, View (tanpa Edit).
- **Create** (`CreateSetoran`), form kolom 1:
  - `Select::make('petugas_id')` "Petugas" — options: petugas KSM ini yang **punya pembayaran belum disetor**
    (label "Nama — Rp total belum disetor"); required; `->live()`;
    `->afterStateUpdated(fn (Set $set, ?string $state) => $set('pembayaran', array_keys(self::opsiPembayaran($state))))` (default semua tercentang).
  - `DatePicker::make('tanggal')` default `Hari::tanggal()`, maxDate hari ini, required.
  - `CheckboxList::make('pembayaran')` "Pembayaran yang diserahkan" — options `self::opsiPembayaran($get('petugas_id'))`:
    `PembayaranIuran::query()->whereBelongsTo(tenant)->where('petugas_id', $id)->belumDisetor()->with('pelanggan')`
    → `[id => "{pelanggan} · {F Y} · Rp…"]`; required, minItems 1, `->bulkToggleable()`, `->live()`, `->visible(filled petugas)`.
    (in-rule otomatis menolak id milik petugas lain / sudah disetor / KSM lain.)
  - `TextEntry::make('total')` live: jumlah nominal yang dicentang.
  - `Textarea::make('catatan')`.
  - `mutateFormDataBeforeCreate`: buang `pembayaran` dari data model (simpan di properti halaman).
  - `handleRecordCreation` dalam `DB::transaction`: buat Setoran (`forceFill(['diterima_oleh_id' => auth()->id()])`, ksm via tenant),
    lalu `PembayaranIuran::whereKey($ids)->where('petugas_id', $petugas)->belumDisetor()->update(['setoran_id' => $setoran->id])`;
    bila jumlah baris terupdate ≠ jumlah `$ids` → rollback + `ValidationException` pada `data.pembayaran`
    ("Sebagian pembayaran sudah disetor di setoran lain — muat ulang.").
- **List**: kolom `tanggal` (date `D, d M Y`, sortable), `petugas.name`, `pembayaran_iuran_count` "Jumlah",
  `total` (`withSum('pembayaranIuran as total', 'nominal')`, money IDR, summarizer Sum), `penerima.name` "Diterima oleh";
  filter petugas & rentang tanggal; aksi baris ActionGroup: `ViewAction`, `DeleteAction` (label "Batalkan setoran",
  `->requiresConfirmation()`, deskripsi "Pembayaran di dalamnya kembali berstatus belum disetor.").
  `DeleteAction` di halaman View juga. Hapus: `before` hook → `pembayaranIuran()->update(['setoran_id' => null])` (FK nullOnDelete sebagai cadangan).
- **View**: infolist (tanggal, petugas, penerima, total, catatan) + `RepeatableEntry` atau tabel relasi pembayaran
  (pelanggan, bulan, nominal, diterima). **[cek]** pilih `RelationManager` read-only `PembayaranIuranRelationManager` bila RepeatableEntry kurang rapi.

### 4.4 Dasbor
- `IuranStatsOverview` (admin): Stat baru **"Di tangan petugas"** = `sum(nominal)` `belumDisetor()` di KSM ini;
  description "belum disetor ke pengurus"; color warning bila > 0.
- `TugasPetugasStatsOverview` (petugas): Stat **"Belum disetor"** = jumlah milik sendiri; description "{n} pembayaran".
- `RiwayatIuranSaya` (warga): kolom "Diterima oleh" = `petugas.name` atau "Pengurus".

## 5. Authorization

| Policy | viewAny | view | create | update | delete | deleteAny |
|---|---|---|---|---|---|---|
| `PembayaranIuranPolicy` (ubah) | admin ∨ petugas | admin ∨ petugas pemilik (`petugas_id`) | admin | admin ∨ §2.3 | (admin ∧ `setoran_id` null) ∨ §2.3 | false |
| `SetoranPolicy` (baru) | admin | admin | admin | false | admin | false |
| `PelangganPolicy::terimaIuran` (baru) | — | — | §2.4 | — | — | — |

Warga: tidak ada akses ke kedua resource (403 dari kebijakan; query `1 = 0`).
`EditRecord`/aksi tabel memeriksa kebijakan saat mount **dan** saat simpan (dicek di Fase 3: `$record` `#[Locked]`,
`EditAction` authorize ulang) → catatan yang masuk setoran saat modal petugas terbuka gagal disimpan.

## 6. Tests (PHPUnit, `Livewire::test`, `travelTo` WITA, `diKsmLain`)

`PetugasIuranTest`:
1. Petugas mencatat 2 bulan di rumah tugasnya → 2 baris, nominal default, `petugas_id` = dia, `ksm_id` benar,
   `dibayar_pada` = hari desa; **warga langsung "Lunas"** di `WargaStatsOverview` (keputusan "langsung lunas").
2. Bulan yang sudah tercatat tidak ditawarkan; mengirim bulan itu / bulan di luar rentang (−7, +3) → error in-rule, 0 baris baru.
3. Nominal diubah ke 15.000 → status bulan itu "Kurang bayar".
4. Rumah bukan tugasnya / nonaktif → `can('terimaIuran')` false; aksi tersembunyi di widget.
5. Tanggal terima 2 hari lalu → ditolak; kemarin → diterima.
6. "Iuran diterima": petugas hanya melihat catatannya; ubah nominal catatan belum disetor → tersimpan;
   setelah masuk setoran → aksi Ubah/Hapus tersembunyi & `can('update')` false; admin masih bisa ubah, **tidak** bisa hapus.
7. Pembayaran admin langsung (petugas_id null) → status "Langsung", tidak pernah muncul di opsi setoran.

`SetoranTest`:
8. Admin pilih petugas → opsi = tepat pembayaran belum-disetor milik petugas itu (bukan petugas lain, bukan yang sudah disetor, bukan KSM lain); default semua tercentang; total benar.
9. Buat setoran → pembayaran terhubung, penerima = admin, total list = jumlah nominal; stat "Di tangan petugas" turun.
10. Id pembayaran petugas lain disisipkan → error in-rule; tidak ada setoran dibuat.
11. Balapan: pembayaran sudah disetor setelah form dibuka → error `pembayaran`, rollback.
12. Batalkan setoran → pembayaran kembali "Belum disetor"; tidak ada pembayaran terhapus.
13. Petugas & warga: 403 di `/setorans`; setoran KSM lain tak terlihat admin.
14. Sabotase (wajib, seperti Fase 3): in-rule bulan, kunci §2.3, filter petugas di query, cek jumlah update setoran, scope tenant — masing-masing harus membuat tes merah.

Semua tes lama tetap hijau (khususnya `IuranModuleTest`, `DashboardPeranTest`).

## 7. Setelah fase
Pint → suite penuh (+ `route:cache` dengan `APP_ROUTES_CACHE` terpisah) → audit keamanan independen →
commit → push → deploy (`sudo -u ksmdesa -i bash -c '$HOME/deploy/deploy.sh'`) → verifikasi → catatan server & Qdrant.
