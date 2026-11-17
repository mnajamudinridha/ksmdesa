<?php

namespace App\Filament\Ksm\Resources\Users;

use App\Enums\PeranPengguna;
use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Ksm\Resources\Users\Pages\ManageUsers;
use App\Filament\Shared\AlihAktifAction;
use App\Filament\Shared\PenggunaForm;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Users of the current KSM (admin, petugas, warga), managed by the village admin.
 * Tenant-scoped through User::ksm(); users are deactivated, never deleted.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $tenantRelationshipName = 'users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    /** Admins and petugas only; warga accounts are managed with their customer (menu Pelanggan). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('peran', PeranPengguna::staf());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(PenggunaForm::komponen(fn () => Filament::getTenant()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('pelangganTugas'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->disk('local')
                    ->visibility('private')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => InisialAvatar::untukNama($record->name)),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('no_hp')
                    ->label('Nomor HP')
                    ->searchable(),
                TextColumn::make('peran')
                    ->badge(),
                TextColumn::make('pelanggan_tugas_count')
                    ->label('Rumah tugas')
                    ->state(fn (User $record): ?int => $record->peran === PeranPengguna::Petugas ? $record->pelanggan_tugas_count : null)
                    ->tooltip('Pelanggan yang ditugaskan ke petugas ini')
                    ->placeholder('—'),
                IconColumn::make('aktif')
                    ->boolean(),
                TextColumn::make('email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('peran')
                    ->options(collect(PeranPengguna::staf())->mapWithKeys(fn (PeranPengguna $peran): array => [$peran->value => $peran->getLabel()])->all()),
                TernaryFilter::make('aktif')
                    ->default(true),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    AlihAktifAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
