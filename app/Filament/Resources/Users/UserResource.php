<?php

namespace App\Filament\Resources\Users;

use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Shared\AlihAktifAction;
use App\Filament\Shared\AvatarField;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Master administrators (super admins): they belong to no KSM, sign in at /admin with
 * email, and may enter every KSM's panel. Only other administrators can add them.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'administrator';

    protected static ?string $pluralModelLabel = 'administrator';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_super_admin', true)->whereNull('ksm_id');
    }

    public static function form(Schema $schema): Schema
    {
        $milikSendiri = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema
            ->columns(2)
            ->components([
                AvatarField::make()
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $dipakai = User::query()
                            ->whereNull('ksm_id')
                            ->where('email', mb_strtolower(trim((string) $value)))
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($dipakai) {
                            $fail('Email sudah dipakai administrator lain.');
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->maxLength(255)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Kosongkan bila tidak diganti.' : null),
                Toggle::make('aktif')
                    ->default(true)
                    // You cannot lock yourself out; disabled fields are not saved.
                    ->disabled($milikSendiri)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
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
                TextColumn::make('email')
                    ->searchable(),
                IconColumn::make('aktif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Ditambahkan')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                AlihAktifAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
