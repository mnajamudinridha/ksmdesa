<?php

namespace App\Filament\Resources\Ksms\Schemas;

use App\Support\SubdomainSlug;
use Closure;
use Filament\Forms\Components\TextInput;
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
            ]);
    }
}
