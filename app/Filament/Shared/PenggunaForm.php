<?php

namespace App\Filament\Shared;

use App\Enums\PeranPengguna;
use App\Models\Ksm;
use App\Models\User;
use App\Support\NomorHp;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Form for a village user, used by master (KSM → Pengguna) and by the village admin
 * (menu Pengguna). Uniqueness is checked on the NORMALISED number/email within the KSM,
 * the same value that is stored — checking the raw input would let "+62812…" pass and then
 * collide with a stored "0812…".
 */
final class PenggunaForm
{
    /**
     * @param  Closure(): ?Ksm  $ksm  the KSM the user belongs to (owner record or current tenant)
     * @return array<int, Component|Field>
     */
    public static function komponen(Closure $ksm): array
    {
        $milikSendiri = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return [
            AvatarField::make()
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            TextInput::make('no_hp')
                ->label('Nomor HP')
                ->tel()
                ->required()
                ->maxLength(20)
                ->rule(fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($ksm, $record): void {
                    $nomor = NomorHp::normalisasi((string) $value);

                    if (! NomorHp::valid($nomor)) {
                        $fail('Nomor HP tidak valid (contoh: 081234567890).');

                        return;
                    }

                    if (self::sudahDipakai($ksm(), 'no_hp', $nomor, $record)) {
                        $fail('Nomor HP sudah dipakai pengguna lain di KSM ini.');
                    }
                })
                ->dehydrateStateUsing(fn (?string $state): ?string => NomorHp::normalisasi($state)),
            TextInput::make('email')
                ->email()
                ->maxLength(255)
                ->helperText('Opsional. Pengguna desa login dengan nomor HP.')
                ->rule(fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($ksm, $record): void {
                    if (filled($value) && self::sudahDipakai($ksm(), 'email', mb_strtolower(trim((string) $value)), $record)) {
                        $fail('Email sudah dipakai pengguna lain di KSM ini.');
                    }
                })
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null),
            TextInput::make('password')
                ->label('Kata sandi')
                ->password()
                ->revealable()
                ->minLength(8)
                ->maxLength(255)
                ->required(fn (string $operation, Get $get, ?User $record): bool => $operation === 'create'
                    || self::naikDariWarga($record, $get('peran')))
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($ksm, $get): void {
                    if (self::peran($get('peran')) !== PeranPengguna::Warga && $ksm()?->cocokSandiDefault((string) $value)) {
                        $fail(self::PESAN_SANDI_DEFAULT);
                    }
                })
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation, Get $get, ?User $record): ?string => match (true) {
                    self::naikDariWarga($record, $get('peran')) => 'Wajib: akun warga yang dinaikkan perannya harus diberi sandi baru (bukan sandi default desa).',
                    $operation === 'edit' => 'Kosongkan bila tidak diganti.',
                    default => null,
                }),
            ToggleButtons::make('peran')
                ->options(PeranPengguna::class)
                ->default(PeranPengguna::Warga)
                ->inline()
                ->required()
                ->live()
                // Disabled fields are not dehydrated, so an admin cannot demote themselves server-side either.
                ->disabled($milikSendiri)
                ->columnSpanFull(),
            Toggle::make('aktif')
                ->default(true)
                ->helperText('Nonaktif = tidak bisa login; riwayatnya tetap tersimpan.')
                ->disabled($milikSendiri),
        ];
    }

    public const PESAN_SANDI_DEFAULT = 'Admin dan petugas tidak boleh memakai sandi default desa — semua warga mengetahuinya.';

    /**
     * A warga account being given another role. Its password may be the village default even
     * without the flag (typed in by hand), and that can't be detected from the hash — so every
     * promotion needs a new password, which the rule above checks against the default.
     */
    private static function naikDariWarga(?User $record, mixed $peran): bool
    {
        $peran = self::peran($peran);

        return $record?->peran === PeranPengguna::Warga && $peran !== null && $peran !== PeranPengguna::Warga;
    }

    private static function peran(mixed $state): ?PeranPengguna
    {
        return $state instanceof PeranPengguna ? $state : PeranPengguna::tryFrom((string) $state);
    }

    private static function sudahDipakai(?Ksm $ksm, string $kolom, ?string $nilai, ?User $kecuali): bool
    {
        return User::query()
            ->where('ksm_id', $ksm?->getKey())
            ->whereNotNull('ksm_id')
            ->where($kolom, $nilai)
            ->when($kecuali, fn ($query) => $query->whereKeyNot($kecuali->getKey()))
            ->exists();
    }
}
