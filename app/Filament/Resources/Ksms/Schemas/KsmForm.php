<?php

namespace App\Filament\Resources\Ksms\Schemas;

use App\Filament\Shared\LogoKsmField;
use App\Filament\Shared\SandiDefaultField;
use App\Support\SubdomainSlug;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class KsmForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas & alamat panel')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama KSM')
                            ->placeholder('KSM Anugerah Makmur Jaya')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('desa')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                                // Suggest a slug from the village name until the admin types their own.
                                if (blank($get('slug')) || $get('slug') === Str::slug((string) $old, '')) {
                                    $set('slug', Str::slug((string) $state, ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Subdomain')
                            ->required()
                            ->maxLength(63)
                            ->unique(ignoreRecord: true)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if ($problem = SubdomainSlug::problem((string) $value)) {
                                    $fail($problem);
                                }
                            })
                            ->suffix('.'.config('app.domain'))
                            ->helperText('Alamat panel KSM. Mengubahnya memutus tautan & login lama.'),
                    ]),
                Section::make('Logo')
                    ->columnSpanFull()
                    ->schema([
                        LogoKsmField::make(),
                    ]),
                Section::make('Sandi default warga')
                    ->description('Akun warga dibuat otomatis dari data pelanggan dan login dengan sandi ini sampai warga menggantinya sendiri.')
                    ->columnSpanFull()
                    ->schema([
                        SandiDefaultField::make(),
                    ]),
            ]);
    }
}
