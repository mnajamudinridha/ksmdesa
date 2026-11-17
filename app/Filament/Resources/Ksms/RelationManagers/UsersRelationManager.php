<?php

namespace App\Filament\Resources\Ksms\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Pengurus: the people who may sign in to this KSM's subdomain.
 * Users are only attached/detached here, never deleted — one person may serve several KSMs.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Pengurus';

    protected static ?string $modelLabel = 'pengurus';

    protected static ?string $pluralModelLabel = 'pengurus';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique('users', 'email', ignoreRecord: true),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->minLength(10)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Kosongkan bila tidak diganti.' : null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('pivot.created_at')
                    ->label('Ditambahkan')
                    ->dateTime('d M Y'),
            ])
            ->headerActions([
                CreateAction::make()->label('Buat akun pengurus'),
                AttachAction::make()
                    ->label('Tambahkan akun yang ada')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email']),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()->label('Lepas'),
            ]);
    }
}
