<?php

namespace App\Filament\Ksm\Resources\Wilayahs;

use App\Filament\Ksm\Resources\Wilayahs\Pages\ManageWilayahs;
use App\Models\Wilayah;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The village's RT/RW list. Customers pick their RT from it; the per-RT reports count against it.
 */
class WilayahResource extends Resource
{
    protected static ?string $model = Wilayah::class;

    protected static ?string $tenantRelationshipName = 'wilayah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Wilayah (RT/RW)';

    protected static ?string $modelLabel = 'wilayah (RT/RW)';

    protected static ?string $pluralModelLabel = 'wilayah (RT/RW)';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isAdminKsm()
            ? $query->withCount('pelanggan')
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('rt')
                    ->label('RT')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(999)
                    ->required()
                    // The database cannot enforce uniqueness when RW is empty (NULL ≠ NULL): this rule does.
                    ->scopedUnique(
                        column: 'rt',
                        ignoreRecord: true,
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => filled($get('rw'))
                            ? $query->where('rw', (int) $get('rw'))
                            : $query->whereNull('rw'),
                    )
                    ->validationMessages(['unique' => 'RT ini sudah ada di daftar.']),
                TextInput::make('rw')
                    ->label('RW')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(999)
                    ->live(onBlur: true),
                TextInput::make('dusun')
                    ->label('Dusun / lingkungan')
                    ->maxLength(100),
                TextInput::make('jumlah_kk')
                    ->label('Jumlah KK')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100_000)
                    ->helperText('Opsional — untuk menghitung persentase KK yang berlangganan.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->urut())
            ->emptyStateHeading('Belum ada RT')
            ->emptyStateDescription('Tambahkan RT/RW desa, lalu tetapkan RT tiap pelanggan di menu Pelanggan.')
            ->columns([
                TextColumn::make('label')
                    ->label('Wilayah')
                    ->state(fn (Wilayah $record): string => $record->label()),
                TextColumn::make('jumlah_kk')
                    ->label('KK')
                    ->numeric(locale: 'id')
                    ->placeholder('—'),
                TextColumn::make('pelanggan_count')
                    ->label('Pelanggan')
                    ->numeric(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription('RT tanpa pelanggan ini akan dihapus.'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWilayahs::route('/'),
        ];
    }
}
